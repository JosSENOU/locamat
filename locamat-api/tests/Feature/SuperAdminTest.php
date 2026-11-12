<?php

namespace Tests\Feature;

use App\Models\AdminAction;
use App\Models\Category;
use App\Models\Materiel;
use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_logs_in_only_through_the_dedicated_endpoint(): void
    {
        User::factory()->superAdmin()->create([
            'email' => 'chef@locamat.bj',
            'password' => 'Password@123',
        ]);
        User::factory()->create([
            'email' => 'client@example.test',
            'password' => 'Password@123',
        ]);

        $this->postJson('/api/super-admin/login', ['email' => 'chef@locamat.bj', 'password' => 'Password@123'])
            ->assertOk()
            ->assertJsonStructure(['user', 'token']);

        $this->postJson('/api/login', ['email' => 'chef@locamat.bj', 'password' => 'Password@123'])
            ->assertUnprocessable();

        $this->postJson('/api/super-admin/login', ['email' => 'client@example.test', 'password' => 'Password@123'])
            ->assertUnprocessable();
    }

    public function test_only_the_super_admin_can_reach_the_super_admin_area(): void
    {
        $this->getJson('/api/super-admin/apercu')->assertUnauthorized();

        foreach ([User::factory()->admin()->create(), User::factory()->create(), User::factory()->proprietaire()->create()] as $autre) {
            $this->actingAs($autre);

            foreach (['apercu', 'journal', 'utilisateurs', 'administrateurs', 'corbeille'] as $ressource) {
                $this->getJson("/api/super-admin/{$ressource}")->assertForbidden();
            }
        }

        $this->actingAs(User::factory()->superAdmin()->create());

        foreach (['apercu', 'journal', 'utilisateurs', 'administrateurs', 'corbeille'] as $ressource) {
            $this->getJson("/api/super-admin/{$ressource}")->assertOk();
        }
    }

    public function test_super_admin_creates_an_administrator_with_a_strong_password(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->postJson('/api/super-admin/administrateurs', [
            'name' => 'Nouvel Admin',
            'email' => 'nouvel-admin@example.test',
            'password' => 'Court1@',
            'password_confirmation' => 'Court1@',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->postJson('/api/super-admin/administrateurs', [
            'name' => 'Nouvel Admin',
            'email' => 'nouvel-admin@example.test',
            'password' => 'Password@1234',
            'password_confirmation' => 'Password@1234',
        ])->assertCreated();

        $admin = User::query()->where('email', 'nouvel-admin@example.test')->firstOrFail();

        $this->assertTrue($admin->hasRole(User::ROLE_ADMIN));
        $this->assertFalse($admin->hasRole(User::ROLE_SUPER_ADMIN));
        $this->assertDatabaseHas('admin_actions', [
            'action' => AdminAction::ADMIN_CREE,
            'cible_id' => $admin->id,
        ]);
    }

    public function test_an_account_deleted_by_an_admin_is_restored_with_its_materials(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin Distrait']);
        $proprietaire = User::factory()->proprietaire()->create(['name' => 'Propriétaire Supprimé']);
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();

        $this->actingAs($admin)
            ->deleteJson("/api/admin/utilisateurs/{$proprietaire->id}")
            ->assertSuccessful();

        $this->assertSoftDeleted('users', ['id' => $proprietaire->id]);
        $this->assertSoftDeleted('materiels', ['id' => $materiel->id]);

        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->getJson('/api/super-admin/corbeille?type=utilisateurs')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Propriétaire Supprimé')
            ->assertJsonPath('data.0.supprime_par.nom', 'Admin Distrait');

        $this->patchJson("/api/super-admin/corbeille/utilisateurs/{$proprietaire->id}/restaurer")->assertOk();

        $this->assertNotSoftDeleted('users', ['id' => $proprietaire->id]);
        $this->assertNotSoftDeleted('materiels', ['id' => $materiel->id]);
        $this->assertDatabaseHas('admin_actions', [
            'action' => AdminAction::COMPTE_RESTAURE,
            'cible_id' => $proprietaire->id,
        ]);
    }

    public function test_an_admin_cannot_restore_or_purge_anything(): void
    {
        $categorie = Category::factory()->create();
        $categorie->delete();

        $this->actingAs(User::factory()->admin()->create());

        $this->patchJson("/api/super-admin/corbeille/categories/{$categorie->id}/restaurer")->assertForbidden();
        $this->deleteJson("/api/super-admin/corbeille/categories/{$categorie->id}")->assertForbidden();

        $this->assertSoftDeleted('categories', ['id' => $categorie->id]);
    }

    public function test_the_purge_command_removes_only_what_exceeded_the_retention_period(): void
    {
        config(['corbeille.retention_days' => 30]);

        $ancienne = Category::factory()->create();
        $recente = Category::factory()->create();
        $ancienne->delete();
        $recente->delete();
        DB::table('categories')->where('id', $ancienne->id)->update(['deleted_at' => now()->subDays(40)]);
        DB::table('categories')->where('id', $recente->id)->update(['deleted_at' => now()->subDays(5)]);

        $compteAncien = User::factory()->create();
        $compteAncien->delete();
        DB::table('users')->where('id', $compteAncien->id)->update(['deleted_at' => now()->subDays(40)]);

        $this->artisan('corbeille:purger')->assertSuccessful();

        $this->assertDatabaseMissing('categories', ['id' => $ancienne->id]);
        $this->assertDatabaseHas('categories', ['id' => $recente->id]);
        $this->assertDatabaseMissing('users', ['id' => $compteAncien->id]);
    }

    public function test_the_command_creates_a_super_admin_and_refuses_a_weak_password(): void
    {
        $this->artisan('super-admin:creer', [
            'email' => 'faible@locamat.bj',
            '--name' => 'Chef',
            '--password' => 'Court1@',
        ])->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'faible@locamat.bj']);

        $this->artisan('super-admin:creer', [
            'email' => 'chef@locamat.bj',
            '--name' => 'Chef',
            '--password' => 'Password@1234',
        ])->assertSuccessful();

        $this->assertTrue(User::query()->where('email', 'chef@locamat.bj')->firstOrFail()->hasRole(User::ROLE_SUPER_ADMIN));
    }

    public function test_the_development_seeder_creates_the_default_super_admin(): void
    {
        $this->seed(SuperAdminSeeder::class);
        $this->seed(SuperAdminSeeder::class);

        $this->assertSame(1, User::query()->where('email', 'superadmin@locamat.bj')->count());

        $this->postJson('/api/super-admin/login', [
            'email' => 'superadmin@locamat.bj',
            'password' => 'Password@123',
        ])->assertOk()
            ->assertJsonStructure(['user', 'token']);
    }
}
