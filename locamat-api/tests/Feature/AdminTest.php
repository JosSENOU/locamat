<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\Materiel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_never_change_the_role_of_an_account(): void
    {
        $admin = User::factory()->admin()->create();
        $locataire = User::factory()->create();

        $this->actingAs($admin)
            ->patchJson("/api/admin/utilisateurs/{$locataire->id}/profil", [
                'name' => 'Nom modifié',
                'role' => User::ROLE_ADMIN,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $this->patchJson("/api/admin/utilisateurs/{$locataire->id}/profil", [
            'name' => 'Nom modifié',
            'roles' => [User::ROLE_PROPRIETAIRE],
        ])->assertUnprocessable()->assertJsonValidationErrors('roles');

        $this->assertTrue($locataire->fresh()->hasRole(User::ROLE_LOCATAIRE));
        $this->assertFalse($locataire->fresh()->hasRole(User::ROLE_ADMIN));
    }

    public function test_admin_can_manage_categories_and_cannot_delete_a_category_in_use(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create(['name' => 'Photo']);

        $this->actingAs($admin)
            ->getJson('/api/admin/categories')
            ->assertOk()
            ->assertJsonPath('0.name', 'Photo')
            ->assertJsonPath('0.materiels_count', 0);

        $this->postJson('/api/admin/categories', ['name' => '  Jardinage  '])
            ->assertCreated()
            ->assertJsonPath('name', 'Jardinage');

        $this->postJson('/api/admin/categories', ['name' => 'Photo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->putJson("/api/admin/categories/{$category->id}", ['name' => 'Photographie'])
            ->assertOk()
            ->assertJsonPath('name', 'Photographie');

        Materiel::factory()->create(['categorie_id' => $category->id]);
        $this->deleteJson("/api/admin/categories/{$category->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Cette catégorie est utilisée par un ou plusieurs matériels et ne peut pas être supprimée.');

        $unusedCategory = Category::factory()->create(['name' => 'Inutilisée']);
        $this->deleteJson("/api/admin/categories/{$unusedCategory->id}")
            ->assertOk();

        $this->assertSoftDeleted('categories', ['id' => $unusedCategory->id]);
    }

    public function test_category_management_is_restricted_to_admins(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/admin/categories')
            ->assertForbidden();

        $this->postJson('/api/admin/categories', ['name' => 'Photo'])
            ->assertForbidden();
    }

    public function test_application_uses_benin_timezone(): void
    {
        $this->assertSame('Africa/Porto-Novo', config('app.timezone'));
    }

    public function test_admin_can_view_platform_statistics_and_filtered_records(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->proprietaire()->create();
        $renter = User::factory()->create(['name' => 'Locataire Recherche']);
        $materiel = Materiel::factory()->for($owner, 'proprietaire')->create([
            'nom' => 'Caméra administrée',
            'disponible' => true,
        ]);
        Location::factory()->for($materiel)->for($renter, 'locataire')->create([
            'statut' => Location::STATUT_EN_ATTENTE,
        ]);
        $otherMateriel = Materiel::factory()->for($owner, 'proprietaire')->create([
            'nom' => 'Vidéoprojecteur',
            'disponible' => false,
        ]);
        Location::factory()->for($otherMateriel)->for($renter, 'locataire')->create([
            'statut' => Location::STATUT_CONFIRMEE,
        ]);

        $this->actingAs($admin)
            ->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonPath('total_utilisateurs', 3)
            ->assertJsonPath('administrateurs', 1)
            ->assertJsonPath('materiels_disponibles', 1)
            ->assertJsonPath('demandes_en_attente', 1);

        $this->actingAs($admin)
            ->getJson('/api/admin/utilisateurs?q=Locataire%20Recherche')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.email', $renter->email)
            ->assertJsonPath('data.0.is_active', true);

        $this->actingAs($admin)
            ->getJson('/api/admin/materiels?q=Cam%C3%A9ra')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.proprietaire_id', $owner->id);

        $this->actingAs($admin)
            ->getJson('/api/admin/locations?statut=en_attente')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.locataire.id', $renter->id)
            ->assertJsonPath('data.0.materiel.id', $materiel->id);

        $this->actingAs($admin)
            ->getJson('/api/admin/locations?q=Cam%C3%A9ra')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.materiel.nom', 'Caméra administrée');

        $this->getJson('/api/admin/locations?q=Cam%C3%A9ra&statut=en_attente')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.materiel.nom', 'Caméra administrée');

        $this->getJson('/api/admin/locations?q=aucun-mat%C3%A9riel-correspondant')
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data');
    }

    public function test_super_admin_can_deactivate_accounts(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $user = User::factory()->create();
        $user->createToken('test');

        $this->actingAs($superAdmin)
            ->patchJson("/api/admin/utilisateurs/{$user->id}/statut", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('is_active', false);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => false]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
    }

    public function test_only_admins_can_unlock_user_accounts(): void
    {
        $user = User::factory()->create([
            'failed_login_attempts' => 3,
            'login_locked_at' => now(),
        ]);
        $nonAdmin = User::factory()->create();

        $this->actingAs($nonAdmin)
            ->patchJson("/api/admin/utilisateurs/{$user->id}/deverrouiller")
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->patchJson("/api/admin/utilisateurs/{$user->id}/deverrouiller")
            ->assertOk()
            ->assertJsonPath('is_locked', false);
    }

    public function test_nobody_can_change_the_role_of_an_account(): void
    {
        $user = User::factory()->create();

        foreach ([User::factory()->admin()->create(), User::factory()->superAdmin()->create()] as $acteur) {
            $this->actingAs($acteur)
                ->patchJson("/api/admin/utilisateurs/{$user->id}/role", ['role' => User::ROLE_ADMIN])
                ->assertNotFound();
        }

        $this->assertTrue($user->fresh()->hasRole(User::ROLE_LOCATAIRE));
        $this->assertFalse($user->fresh()->hasRole(User::ROLE_ADMIN));
    }

    public function test_disabled_users_cannot_log_in(): void
    {
        $user = User::factory()->create([
            'email' => 'suspended@example.test',
            'password' => 'password123',
            'is_active' => false,
        ]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertForbidden()
            ->assertJsonPath('message', 'Ce compte est désactivé. Contactez l’assistance pour obtenir de l’aide.');
    }

    public function test_admin_cannot_change_own_access_and_only_the_super_admin_can_deactivate_another_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $anotherAdmin = User::factory()->admin()->create();
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->patchJson("/api/admin/utilisateurs/{$admin->id}/statut", ['is_active' => false])
            ->assertUnprocessable()
            ->assertJsonPath('errors.is_active.0', 'Vous ne pouvez pas désactiver votre propre compte.');

        $this->patchJson("/api/admin/utilisateurs/{$anotherAdmin->id}/statut", ['is_active' => false])
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->patchJson("/api/admin/utilisateurs/{$anotherAdmin->id}/statut", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('is_active', false);

        $this->assertTrue($admin->fresh()->hasRole(User::ROLE_ADMIN));
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'is_active' => true]);
    }

    public function test_only_an_administrator_can_access_admin_endpoints(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/admin/stats')
            ->assertForbidden();

        Auth::forgetGuards();
        $this->getJson('/api/admin/utilisateurs')
            ->assertUnauthorized();
    }

    public function test_admin_can_edit_the_profile_and_location_of_an_existing_owner(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->proprietaire()->create([
            'ville' => null,
            'quartier' => null,
            'adresse' => null,
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/admin/utilisateurs/{$owner->id}/profil", [
                'name' => 'Nouveau Nom',
                'telephone' => '+229 01 23 45 67 89',
                'ville' => 'Cotonou',
                'quartier' => 'Haie Vive',
                'adresse' => 'En face de la pharmacie',
            ])
            ->assertOk()
            ->assertJsonPath('name', 'Nouveau Nom')
            ->assertJsonPath('ville', 'Cotonou')
            ->assertJsonPath('quartier', 'Haie Vive')
            ->assertJsonPath('adresse', 'En face de la pharmacie');

        $this->assertDatabaseHas('users', [
            'id' => $owner->id,
            'name' => 'Nouveau Nom',
            'ville' => 'Cotonou',
            'quartier' => 'Haie Vive',
            'adresse' => 'En face de la pharmacie',
        ]);
    }

    public function test_owner_profile_requires_a_city_and_a_phone_and_location_is_refused_for_others(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->proprietaire()->create();
        $renter = User::factory()->create();

        $this->actingAs($admin)
            ->patchJson("/api/admin/utilisateurs/{$owner->id}/profil", ['name' => 'Propriétaire'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['telephone', 'ville']);

        $this->patchJson("/api/admin/utilisateurs/{$renter->id}/profil", [
            'name' => 'Locataire',
            'ville' => 'Cotonou',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.ville.0', 'La localisation ne concerne que les propriétaires.');

        $this->patchJson("/api/admin/utilisateurs/{$renter->id}/profil", [
            'name' => 'Locataire Modifié',
            'telephone' => '+229 01 20 00 00 00',
        ])
            ->assertOk()
            ->assertJsonPath('name', 'Locataire Modifié');
    }

    public function test_a_non_admin_cannot_edit_a_user_profile(): void
    {
        $owner = User::factory()->proprietaire()->create();

        $this->actingAs($owner)
            ->patchJson("/api/admin/utilisateurs/{$owner->id}/profil", ['name' => 'X', 'ville' => 'Cotonou'])
            ->assertForbidden();
    }

    public function test_admin_can_hide_a_material_without_deleting_it(): void
    {
        $admin = User::factory()->admin()->create();
        $materiel = Materiel::factory()->create(['disponible' => true]);

        $this->actingAs($admin)
            ->patchJson("/api/admin/materiels/{$materiel->id}/disponibilite", ['disponible' => false])
            ->assertOk()
            ->assertJsonPath('disponible', false);

        $this->assertDatabaseHas('materiels', ['id' => $materiel->id, 'disponible' => false]);
    }
}
