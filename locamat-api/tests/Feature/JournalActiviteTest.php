<?php

namespace Tests\Feature;

use App\Models\AdminAction;
use App\Models\Category;
use App\Models\Materiel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class JournalActiviteTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_activity_is_journaled_and_can_be_filtered_by_user(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Nouvelle Cliente',
            'email' => 'cliente@example.test',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'role' => User::ROLE_LOCATAIRE,
        ])->assertCreated();

        $cliente = User::query()->where('email', 'cliente@example.test')->firstOrFail();
        $cliente->markEmailAsVerified();

        $jeton = $this->postJson('/api/login', ['email' => 'cliente@example.test', 'password' => 'Password@123'])->assertOk()->json('token');
        $this->withToken($jeton)->putJson('/api/profile', ['name' => 'Cliente Renommée'])->assertOk();
        $this->withToken($jeton)->postJson('/api/logout')->assertOk();
        $this->flushHeaders();
        Auth::forgetGuards();

        $superAdmin = User::factory()->superAdmin()->create();

        $reponse = $this->actingAs($superAdmin)
            ->getJson("/api/super-admin/journal?utilisateur={$cliente->id}")
            ->assertOk();

        $actions = collect($reponse->json('data'))->pluck('action')->all();

        $this->assertEqualsCanonicalizing(
            [
                AdminAction::INSCRIPTION,
                AdminAction::CONNEXION,
                AdminAction::PROFIL_PERSONNEL_MODIFIE,
                AdminAction::DECONNEXION,
            ],
            $actions,
        );
    }

    public function test_locked_account_and_password_changes_are_journaled(): void
    {
        $user = User::factory()->create(['email' => 'verrou@example.test', 'password' => 'Password@123']);

        foreach (range(1, 3) as $essai) {
            $this->postJson('/api/login', ['email' => 'verrou@example.test', 'password' => 'mauvais']);
        }

        $this->assertDatabaseHas('admin_actions', [
            'action' => AdminAction::COMPTE_BLOQUE,
            'cible_id' => $user->id,
            'user_id' => null,
            'acteur_nom' => 'Système',
        ]);

        $autre = User::factory()->create(['password' => 'Password@123']);

        $this->actingAs($autre)->postJson('/api/change-password', [
            'current_password' => 'Password@123',
            'password' => 'Nouveau@Pass456',
            'password_confirmation' => 'Nouveau@Pass456',
        ])->assertOk();

        $this->assertTrue(Hash::check('Nouveau@Pass456', $autre->fresh()->password));
        $this->assertDatabaseHas('admin_actions', [
            'action' => AdminAction::MOT_DE_PASSE_MODIFIE,
            'user_id' => $autre->id,
        ]);
    }

    public function test_rental_events_appear_in_the_history_of_both_the_tenant_and_the_owner(): void
    {
        $this->travelTo('2026-11-03 12:00:00');
        $locataire = User::factory()->create(['name' => 'Locataire Test']);
        $proprietaire = User::factory()->proprietaire()->create(['name' => 'Propriétaire Test']);
        $tiers = User::factory()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create(['nom' => 'Perceuse Pro']);

        $locationId = $this->actingAs($locataire)->postJson('/api/locations', [
            'materiel_id' => $materiel->id,
            'date_debut' => '2026-11-10',
            'date_fin' => '2026-11-12',
        ])->assertCreated()->json('id');

        $this->actingAs($proprietaire)
            ->patchJson("/api/proprietaire/locations/{$locationId}/confirmer")
            ->assertOk();

        $superAdmin = User::factory()->superAdmin()->create();
        $this->actingAs($superAdmin);

        foreach ([$locataire, $proprietaire] as $participant) {
            $actions = collect($this->getJson("/api/super-admin/journal?utilisateur={$participant->id}")->json('data'))
                ->pluck('action')
                ->all();

            $this->assertContains(AdminAction::LOCATION_DEMANDEE, $actions);
            $this->assertContains(AdminAction::LOCATION_CONFIRMEE, $actions);
        }

        $this->getJson("/api/super-admin/journal?utilisateur={$tiers->id}")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/super-admin/journal?action=location_demandee')
            ->assertOk()
            ->assertJsonPath('data.0.cible_libelle', 'Perceuse Pro')
            ->assertJsonPath('data.0.acteur_nom', 'Locataire Test');
    }

    public function test_super_admin_lists_every_account_with_its_activity(): void
    {
        $actif = User::factory()->create(['name' => 'Compte Actif', 'email' => 'actif@example.test', 'password' => 'Password@123']);
        $supprime = User::factory()->create(['name' => 'Compte Supprimé']);
        $supprime->delete();
        $this->postJson('/api/login', ['email' => 'actif@example.test', 'password' => 'Password@123'])->assertOk();
        Auth::forgetGuards();

        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/api/super-admin/utilisateurs')
            ->assertForbidden();

        $reponse = $this->actingAs(User::factory()->superAdmin()->create())
            ->getJson('/api/super-admin/utilisateurs?q=Compte')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $lignes = collect($reponse->json('data'))->keyBy('name');

        $this->assertSame(1, $lignes['Compte Actif']['actions_count']);
        $this->assertNotNull($lignes['Compte Actif']['derniere_activite']);
        $this->assertTrue($lignes['Compte Supprimé']['supprime']);
        $this->assertSame(0, $lignes['Compte Supprimé']['actions_count']);
    }

    public function test_a_category_deleted_by_an_admin_can_be_restored_by_the_super_admin(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin Distrait']);
        $categorie = Category::factory()->create(['name' => 'Bricolage']);

        $this->actingAs($admin)
            ->deleteJson("/api/admin/categories/{$categorie->id}")
            ->assertOk();

        $this->assertSoftDeleted('categories', ['id' => $categorie->id]);
        $this->getJson('/api/admin/categories')->assertJsonCount(0);
        $this->postJson('/api/admin/categories', ['name' => 'Bricolage'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->actingAs($admin)->getJson('/api/super-admin/corbeille?type=categories')->assertForbidden();

        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->getJson('/api/super-admin/corbeille?type=categories')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Bricolage')
            ->assertJsonPath('data.0.supprime_par.nom', 'Admin Distrait')
            ->assertJsonPath('data.0.supprime_par.role', User::ROLE_ADMIN);

        $this->patchJson("/api/super-admin/corbeille/categories/{$categorie->id}/restaurer")->assertOk();

        $this->assertNotSoftDeleted('categories', ['id' => $categorie->id]);
        $this->assertDatabaseHas('admin_actions', ['action' => AdminAction::CATEGORIE_RESTAUREE, 'cible_id' => $categorie->id]);
    }

    public function test_a_material_deleted_by_an_admin_shows_who_deleted_it(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin Distrait']);
        $materiel = Materiel::factory()->create(['nom' => 'Scie circulaire']);

        $this->actingAs($admin)->deleteJson("/api/admin/materiels/{$materiel->id}")->assertOk();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->getJson('/api/super-admin/corbeille?type=materiels')
            ->assertOk()
            ->assertJsonPath('data.0.supprime_par.nom', 'Admin Distrait');
    }

    public function test_registration_enforces_the_password_rules_with_a_clear_message(): void
    {
        $payload = [
            'name' => 'Test',
            'email' => 'regles@example.test',
            'role' => User::ROLE_LOCATAIRE,
        ];

        foreach (['Ab1@xyz', 'sansmajuscule1@', 'SANSMINUSCULE1@', 'SansChiffre@@', 'SansSpecial123', 'Avec Espace1@'] as $faible) {
            $this->postJson('/api/register', $payload + ['password' => $faible, 'password_confirmation' => $faible])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('password');
        }

        $this->postJson('/api/register', $payload + ['password' => 'Password@123', 'password_confirmation' => 'Password@123'])
            ->assertCreated();
    }
}
