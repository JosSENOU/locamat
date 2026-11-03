<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_a_locataire_and_returns_a_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Alex Client',
            'email' => 'alex@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.role', User::ROLE_LOCATAIRE)
            ->assertJsonStructure(['user' => ['id', 'name', 'email', 'role'], 'token'])
            ->assertJsonMissingPath('user.password');

        $this->assertDatabaseHas('users', [
            'email' => 'alex@example.com',
            'role' => User::ROLE_LOCATAIRE,
        ]);
    }

    public function test_registration_can_create_a_proprietaire_but_not_an_admin(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Proprietaire',
            'email' => 'owner@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => User::ROLE_PROPRIETAIRE,
        ])->assertCreated()
            ->assertJsonPath('user.role', User::ROLE_PROPRIETAIRE);

        $this->postJson('/api/register', [
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => User::ROLE_ADMIN,
        ])->assertUnprocessable();
    }

    public function test_validation_errors_use_the_french_api_contract(): void
    {
        $this->postJson('/api/register', [])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['name', 'email', 'password']])
            ->assertJsonPath('message', 'Le champ nom est obligatoire.')
            ->assertJsonPath('errors.name.0', 'Le champ nom est obligatoire.');
    }

    public function test_registration_can_save_the_users_telephone(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Proprietaire',
            'email' => 'contact@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => User::ROLE_PROPRIETAIRE,
            'telephone' => '+33123456789',
        ])->assertCreated()
            ->assertJsonPath('user.telephone', '+33123456789');

        $this->assertDatabaseHas('users', [
            'email' => 'contact@example.com',
            'telephone' => '+33123456789',
        ]);
    }

    public function test_login_returns_a_token_and_invalid_credentials_are_rejected(): void
    {
        User::factory()->create([
            'email' => 'alex@example.com',
            'password' => 'password123',
        ]);

        $this->postJson('/api/login', [
            'email' => 'alex@example.com',
            'password' => 'password123',
        ])->assertOk()
            ->assertJsonStructure(['user', 'token']);

        $this->postJson('/api/login', [
            'email' => 'alex@example.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable();
    }

    public function test_authenticated_user_can_logout_and_revoke_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $tokenId = $user->tokens()->firstOrFail()->getKey();

        $this->withToken($token)->postJson('/api/logout')->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_role_middleware_allows_only_the_required_role(): void
    {
        Route::get('/api/test/proprietaire', fn () => response()->json())
            ->middleware(['auth:sanctum', 'role:proprietaire']);

        $locataire = User::factory()->create(['role' => User::ROLE_LOCATAIRE]);
        $proprietaire = User::factory()->create(['role' => User::ROLE_PROPRIETAIRE]);

        $this->actingAs($locataire)
            ->getJson('/api/test/proprietaire')
            ->assertForbidden()
            ->assertJsonStructure(['message', 'errors']);
        $this->actingAs($proprietaire)->getJson('/api/test/proprietaire')->assertOk();
    }

    public function test_authenticated_user_resource_never_returns_the_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonMissingPath('password');
    }

    public function test_authenticated_user_can_update_their_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Ancien nom',
            'telephone' => '+33111111111',
        ]);

        $this->actingAs($user)
            ->putJson('/api/profile', [
                'name' => 'Nouveau nom',
                'telephone' => '+22912345678',
            ])
            ->assertOk()
            ->assertJsonPath('name', 'Nouveau nom')
            ->assertJsonPath('email', $user->email)
            ->assertJsonPath('role', $user->role)
            ->assertJsonPath('telephone', '+22912345678')
            ->assertJsonMissingPath('password');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Nouveau nom',
            'telephone' => '+22912345678',
        ]);
    }

    public function test_profile_update_allows_clearing_the_telephone_and_requires_a_name(): void
    {
        $user = User::factory()->create(['telephone' => '+22912345678']);

        $this->actingAs($user)
            ->putJson('/api/profile', ['name' => 'Nom conservé', 'telephone' => null])
            ->assertOk()
            ->assertJsonPath('telephone', null);

        $this->putJson('/api/profile', ['telephone' => null])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['name']]);
    }

    public function test_tenant_can_upload_replace_and_remove_their_profile_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => User::ROLE_LOCATAIRE]);

        $this->actingAs($user)
            ->post('/api/profile', [
                '_method' => 'PUT',
                'name' => $user->name,
                'telephone' => '',
                'photo' => UploadedFile::fake()->image('portrait.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('photo', fn (?string $photo): bool => is_string($photo) && str_starts_with($photo, 'profiles/'));

        $user->refresh();
        $oldPhoto = $user->photo;
        $this->assertNotNull($oldPhoto);
        Storage::disk('public')->assertExists($oldPhoto);

        $this->post('/api/profile', [
            '_method' => 'PUT',
            'name' => $user->name,
            'telephone' => '',
            'photo' => UploadedFile::fake()->image('portrait.webp'),
        ], ['Accept' => 'application/json'])
            ->assertOk();

        $user->refresh();
        $this->assertNotSame($oldPhoto, $user->photo);
        Storage::disk('public')->assertMissing($oldPhoto);
        Storage::disk('public')->assertExists($user->photo);

        $currentPhoto = $user->photo;
        $this->putJson('/api/profile', [
            'name' => $user->name,
            'telephone' => null,
            'remove_photo' => true,
        ])->assertOk()->assertJsonPath('photo', null);

        Storage::disk('public')->assertMissing($currentPhoto);
    }

    public function test_admin_cannot_upload_or_remove_a_profile_photo(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->post('/api/profile', [
                '_method' => 'PUT',
                'name' => $admin->name,
                'photo' => UploadedFile::fake()->image('portrait.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['photo']]);

        $this->actingAs($admin)
            ->putJson('/api/profile', [
                'name' => $admin->name,
                'remove_photo' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['remove_photo']]);

        Storage::disk('public')->assertDirectoryEmpty('profiles');
    }

    public function test_profile_updates_without_a_photo_keep_the_existing_photo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('profiles/existing.jpg', 'existing profile photo');
        $user = User::factory()->create([
            'role' => User::ROLE_PROPRIETAIRE,
            'photo' => 'profiles/existing.jpg',
        ]);

        $this->actingAs($user)
            ->putJson('/api/profile', ['name' => 'Nom mis à jour', 'telephone' => null])
            ->assertOk()
            ->assertJsonPath('photo', 'profiles/existing.jpg');

        Storage::disk('public')->assertExists('profiles/existing.jpg');
    }

    public function test_admin_user_resource_never_exposes_a_profile_photo(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'photo' => 'profiles/admin.jpg',
        ]);
        Storage::disk('public')->put('profiles/admin.jpg', 'unused');

        $this->actingAs($admin)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('photo', null);

        Storage::disk('public')->assertExists('profiles/admin.jpg');
    }

    public function test_profile_photo_rejects_unsupported_files(): void
    {
        Storage::fake('public');
        $owner = User::factory()->proprietaire()->create();

        $this->actingAs($owner)
            ->post('/api/profile', [
                '_method' => 'PUT',
                'name' => $owner->name,
                'photo' => UploadedFile::fake()->image('owner-portrait.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('role', User::ROLE_PROPRIETAIRE);

        $owner->refresh();
        $this->assertNotNull($owner->photo);
        Storage::disk('public')->assertExists($owner->photo);

        $this->actingAs($owner)
            ->post('/api/profile', [
                '_method' => 'PUT',
                'name' => $owner->name,
                'photo' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['photo']]);
    }
}
