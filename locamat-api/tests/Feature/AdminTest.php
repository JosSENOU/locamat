<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Materiel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_platform_statistics_and_filtered_records(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
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

    public function test_admin_can_change_roles_and_deactivate_accounts(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->create();
        $user->createToken('test');

        $this->actingAs($admin)
            ->patchJson("/api/admin/utilisateurs/{$user->id}/role", ['role' => User::ROLE_ADMIN])
            ->assertOk()
            ->assertJsonPath('role', User::ROLE_ADMIN);

        $this->patchJson("/api/admin/utilisateurs/{$user->id}/statut", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('is_active', false);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => false]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
    }

    public function test_promoting_a_user_to_admin_removes_their_profile_photo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('profiles/user.jpg', 'profile');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->create([
            'role' => User::ROLE_LOCATAIRE,
            'photo' => 'profiles/user.jpg',
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/admin/utilisateurs/{$user->id}/role", ['role' => User::ROLE_ADMIN])
            ->assertOk()
            ->assertJsonPath('role', User::ROLE_ADMIN)
            ->assertJsonPath('photo', null);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => User::ROLE_ADMIN, 'photo' => null]);
        Storage::disk('public')->assertMissing('profiles/user.jpg');
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

    public function test_admin_cannot_change_own_access_and_another_admin_can_be_deactivated_safely(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $anotherAdmin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->patchJson("/api/admin/utilisateurs/{$admin->id}/statut", ['is_active' => false])
            ->assertUnprocessable()
            ->assertJsonPath('errors.is_active.0', 'Vous ne pouvez pas désactiver votre propre compte.');

        $this->patchJson("/api/admin/utilisateurs/{$admin->id}/role", ['role' => User::ROLE_LOCATAIRE])
            ->assertUnprocessable()
            ->assertJsonPath('errors.role.0', 'Vous ne pouvez pas modifier votre propre rôle.');

        $this->patchJson("/api/admin/utilisateurs/{$anotherAdmin->id}/statut", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('is_active', false);

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => User::ROLE_ADMIN, 'is_active' => true]);
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

    public function test_admin_can_hide_a_material_without_deleting_it(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $materiel = Materiel::factory()->create(['disponible' => true]);

        $this->actingAs($admin)
            ->patchJson("/api/admin/materiels/{$materiel->id}/disponibilite", ['disponible' => false])
            ->assertOk()
            ->assertJsonPath('disponible', false);

        $this->assertDatabaseHas('materiels', ['id' => $materiel->id, 'disponible' => false]);
    }
}
