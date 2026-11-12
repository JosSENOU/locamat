<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\LockedAccountSupportReceived;
use App\Notifications\LockedAccountSupportRequestNotification;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_requires_email_verification_before_login(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/register', [
            'name' => 'Alex Client',
            'email' => 'alex@example.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('email', 'alex@example.com')
            ->assertJsonMissingPath('token');

        $user = User::where('email', 'alex@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole(User::ROLE_LOCATAIRE));
        $this->assertNull($user->email_verified_at);
        $this->assertTrue(Hash::check('Password@123', $user->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
        Notification::assertSentTo($user, VerifyEmailNotification::class);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'Password@123',
        ])->assertForbidden()
            ->assertJsonPath('code', 'email_not_verified');
    }

    public function test_signed_email_verification_link_activates_the_account(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(30),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
        );

        $this->get($url)
            ->assertRedirect(config('app.frontend_url').'/verification-email?verified=1');

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()
            ->assertJsonStructure(['user', 'token']);
    }

    public function test_unverified_user_can_request_a_new_verification_link(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->postJson('/api/email/verification-notification', ['email' => $user->email])
            ->assertOk()
            ->assertJsonStructure(['message']);

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_locataire_can_add_the_proprietaire_role_later(): void
    {
        $locataire = User::factory()->create();

        $this->actingAs($locataire)
            ->postJson('/api/profile/become-owner', [
                'telephone' => '+229 01 97 00 00 02',
                'ville' => 'Cotonou',
                'quartier' => 'Haie Vive',
                'adresse' => 'Rue des Palmiers',
            ])
            ->assertOk()
            ->assertJsonPath('role', User::ROLE_PROPRIETAIRE)
            ->assertJsonPath('roles', [User::ROLE_LOCATAIRE, User::ROLE_PROPRIETAIRE])
            ->assertJsonPath('telephone', '+229 01 97 00 00 02')
            ->assertJsonPath('ville', 'Cotonou');

        $this->assertTrue($locataire->fresh()->hasRole(User::ROLE_LOCATAIRE, User::ROLE_PROPRIETAIRE));
    }

    public function test_database_uses_role_category_and_photo_relations(): void
    {
        $this->assertFalse(Schema::hasColumn('users', 'role'));
        $this->assertTrue(Schema::hasColumn('users', 'failed_login_attempts'));
        $this->assertTrue(Schema::hasColumn('users', 'login_locked_at'));
        $this->assertFalse(Schema::hasColumn('materiels', 'proprietaire_id'));
        $this->assertFalse(Schema::hasColumn('materiels', 'categorie'));
        $this->assertFalse(Schema::hasColumn('materiels', 'photo'));
        $this->assertFalse(Schema::hasColumn('locations', 'locataire_id'));
        $this->assertTrue(Schema::hasColumn('materiels', 'user_id'));
        $this->assertTrue(Schema::hasColumn('locations', 'user_id'));
        $this->assertTrue(Schema::hasColumn('locations', 'prolongation_date_fin'));
        $this->assertTrue(Schema::hasColumn('locations', 'prolongation_montant'));
        $this->assertTrue(Schema::hasColumn('locations', 'prolongation_statut'));
        $this->assertTrue(Schema::hasTable('roles'));
        $this->assertTrue(Schema::hasTable('role_user'));
        $this->assertTrue(Schema::hasTable('categories'));
        $this->assertTrue(Schema::hasTable('materiel_photos'));
    }

    public function test_registration_can_create_a_proprietaire_but_not_an_admin(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Proprietaire',
            'email' => 'owner@example.com',
            'telephone' => '+229 01 97 00 00 00',
            'ville' => 'Cotonou',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'role' => User::ROLE_PROPRIETAIRE,
        ])->assertCreated()
            ->assertJsonPath('email', 'owner@example.com');

        $this->assertTrue(User::where('email', 'owner@example.com')->firstOrFail()->hasRole(User::ROLE_PROPRIETAIRE));

        $this->postJson('/api/register', [
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'role' => User::ROLE_ADMIN,
        ])->assertUnprocessable();
    }

    public function test_registration_supports_both_tenant_and_owner_roles(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Double rôle',
            'email' => 'both@example.com',
            'telephone' => '+229 01 97 00 00 01',
            'ville' => 'Cotonou',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'roles' => [User::ROLE_LOCATAIRE, User::ROLE_PROPRIETAIRE],
        ])->assertCreated()
            ->assertJsonPath('email', 'both@example.com');

        $this->assertTrue(User::where('email', 'both@example.com')->firstOrFail()->hasRole(User::ROLE_LOCATAIRE, User::ROLE_PROPRIETAIRE));
    }

    public function test_registration_rejects_a_phone_number_outside_the_benin_format(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Proprietaire',
            'email' => 'owner@example.com',
            'telephone' => '+22997000000',
            'ville' => 'Cotonou',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'role' => User::ROLE_PROPRIETAIRE,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('telephone');
    }

    public function test_registration_rejects_a_phone_number_with_an_unassigned_benin_prefix(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Proprietaire',
            'email' => 'invalid-prefix@example.com',
            'telephone' => '+229 01 25 12 34 56',
            'ville' => 'Cotonou',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'role' => User::ROLE_PROPRIETAIRE,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('telephone');
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
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'role' => User::ROLE_PROPRIETAIRE,
            'telephone' => '+229 01 23 45 67 89',
            'ville' => 'Cotonou',
        ])->assertCreated()
            ->assertJsonPath('email', 'contact@example.com');

        $this->assertDatabaseHas('users', [
            'email' => 'contact@example.com',
            'telephone' => '+229 01 23 45 67 89',
        ]);
    }

    public function test_login_returns_a_token_and_invalid_credentials_are_rejected(): void
    {
        User::factory()->create([
            'email' => 'alex@example.com',
            'password' => 'Password@123',
        ]);

        $this->postJson('/api/login', [
            'email' => 'alex@example.com',
            'password' => 'Password@123',
        ])->assertOk()
            ->assertJsonStructure(['user', 'token']);

        $this->postJson('/api/login', [
            'email' => 'alex@example.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable();
    }

    public function test_three_incorrect_passwords_lock_account_until_admin_unlocks_it(): void
    {
        $this->travelTo('2026-11-11 10:00:00');
        $user = User::factory()->create([
            'email' => 'locked@example.com',
            'password' => 'correct-password',
        ]);
        $admin = User::factory()->admin()->create();

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'incorrect-password',
        ])->assertUnprocessable();

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'incorrect-password',
        ])->assertUnprocessable();

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'incorrect-password',
        ])->assertStatus(423)
            ->assertJsonPath('message', 'Votre compte est bloqué après trois tentatives incorrectes.')
            ->assertJsonStructure(['locked_until']);

        $this->assertSame(3, $user->fresh()->failed_login_attempts);
        $this->assertNotNull($user->fresh()->login_locked_at);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ])->assertStatus(423);

        $this->actingAs($admin)
            ->patchJson("/api/admin/utilisateurs/{$user->id}/deverrouiller")
            ->assertOk()
            ->assertJsonPath('is_locked', false);

        $this->assertSame(0, $user->fresh()->failed_login_attempts);
        $this->assertNull($user->fresh()->login_locked_at);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ])->assertOk()
            ->assertJsonStructure(['user', 'token']);
    }

    public function test_successful_login_resets_failed_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'reset-attempts@example.com',
            'password' => 'correct-password',
            'failed_login_attempts' => 2,
        ]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ])->assertOk();

        $this->assertSame(0, $user->fresh()->failed_login_attempts);
        $this->assertNull($user->fresh()->login_locked_at);
    }

    public function test_existing_unverified_users_cannot_log_in(): void
    {
        $user = User::factory()->unverified()->create();

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertForbidden()
            ->assertJsonPath('code', 'email_not_verified')
            ->assertJsonMissingPath('token');
    }

    public function test_locked_user_can_send_a_support_request_and_receives_an_email_confirmation(): void
    {
        Notification::fake();
        $this->travelTo('2026-11-11 10:00:00');
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create([
            'name' => 'Compte bloqué',
            'email' => 'locked-support@example.com',
            'failed_login_attempts' => 3,
            'login_locked_at' => now()->subMinutes(10),
        ]);

        $response = $this->postJson('/api/account-lock/support', [
            'email' => $user->email,
            'message' => 'Mon compte est bloqué, merci de m’aider.',
        ])->assertOk()
            ->assertJsonPath('message', 'Votre demande a été envoyée à l’administrateur.')
            ->assertJsonStructure(['locked_until']);

        $this->assertEquals(
            now()->subMinutes(10)->addHours(12)->timestamp,
            Carbon::parse($response->json('locked_until'))->timestamp,
        );
        Notification::assertSentTo($admin, LockedAccountSupportRequestNotification::class);
        Notification::assertSentTo($user, LockedAccountSupportReceived::class);
    }

    public function test_support_request_is_rejected_if_the_account_is_not_locked(): void
    {
        Notification::fake();
        User::factory()->admin()->create();
        $user = User::factory()->create(['email' => 'not-locked@example.com']);

        $this->postJson('/api/account-lock/support', [
            'email' => $user->email,
            'message' => 'Mon compte est bloqué, merci de m’aider.',
        ])->assertUnprocessable();

        Notification::assertNothingSent();
    }

    public function test_account_lock_expires_after_twelve_hours(): void
    {
        $this->travelTo('2026-11-11 10:00:00');
        $user = User::factory()->create([
            'email' => 'expired-lock@example.com',
            'password' => 'correct-password',
            'failed_login_attempts' => 3,
            'login_locked_at' => now()->subHours(12),
        ]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ])->assertOk()
            ->assertJsonStructure(['user', 'token']);

        $this->assertSame(0, $user->fresh()->failed_login_attempts);
        $this->assertNull($user->fresh()->login_locked_at);
    }

    public function test_password_reset_code_is_sent_verified_and_can_reset_the_password(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => 'old-password']);

        $this->postJson('/api/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('message', 'Si un compte correspond à cette adresse, un code de vérification lui a été envoyé.');

        $code = null;
        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            function (ResetPasswordNotification $notification) use ($user, &$code): bool {
                foreach ($notification->toMail($user)->introLines as $line) {
                    if (preg_match('/^\d{6}$/', $line) === 1) {
                        $code = $line;

                        return true;
                    }
                }

                return false;
            },
        );
        $this->assertNotNull($code);

        $verification = $this->postJson('/api/verify-reset-code', [
            'email' => $user->email,
            'code' => $code,
        ])->assertOk()->assertJsonStructure(['token']);

        $oldToken = $user->createToken('old-session')->plainTextToken;
        $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'token' => $verification->json('token'),
            'password' => 'Nouveau@Pass456',
            'password_confirmation' => 'Nouveau@Pass456',
        ])->assertOk();

        $this->assertTrue(Hash::check('Nouveau@Pass456', $user->fresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->withToken($oldToken)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_invalid_and_expired_password_reset_codes_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();

        $this->postJson('/api/verify-reset-code', [
            'email' => $user->email,
            'code' => '000000',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'Vérifiez le code reçu par e-mail ou demandez-en un nouveau.');

        DB::table('password_reset_tokens')->where('email', $user->email)->update([
            'token' => Hash::make('123456'),
            'created_at' => now()->subMinutes(11),
        ]);

        $this->postJson('/api/verify-reset-code', [
            'email' => $user->email,
            'code' => '123456',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'Vérifiez le code reçu par e-mail ou demandez-en un nouveau.');
    }

    public function test_password_reset_code_verification_is_limited_to_five_attempts(): void
    {
        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/verify-reset-code', [
                'email' => $user->email,
                'code' => '000000',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/verify-reset-code', [
            'email' => $user->email,
            'code' => '000000',
        ])->assertTooManyRequests();
    }

    public function test_authenticated_user_can_change_their_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);

        $this->actingAs($user)->postJson('/api/change-password', [
            'current_password' => 'old-password',
            'password' => 'Nouveau@Pass456',
            'password_confirmation' => 'Nouveau@Pass456',
        ])->assertOk();

        $this->assertTrue(Hash::check('Nouveau@Pass456', $user->fresh()->password));
    }

    public function test_legacy_md5_password_is_upgraded_after_successful_login(): void
    {
        $user = User::factory()->create(['password' => 'legacy-password']);
        DB::table('users')->where('id', $user->id)->update([
            'password' => md5('legacy-password'),
        ]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'legacy-password',
        ])->assertOk();

        $upgradedPassword = $user->fresh()->password;
        $this->assertNotSame(md5('legacy-password'), $upgradedPassword);
        $this->assertTrue(Hash::check('legacy-password', $upgradedPassword));
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

        $locataire = User::factory()->create();
        $proprietaire = User::factory()->proprietaire()->create();

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
                'telephone' => '+229 01 23 45 67 89',
            ])
            ->assertOk()
            ->assertJsonPath('name', 'Nouveau nom')
            ->assertJsonPath('email', $user->email)
            ->assertJsonPath('role', $user->primaryRole())
            ->assertJsonPath('telephone', '+229 01 23 45 67 89')
            ->assertJsonMissingPath('password');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Nouveau nom',
            'telephone' => '+229 01 23 45 67 89',
        ]);
    }

    public function test_profile_update_allows_clearing_the_telephone_and_requires_a_name(): void
    {
        $user = User::factory()->create(['telephone' => '+229 01 23 45 67 89']);

        $this->actingAs($user)
            ->putJson('/api/profile', ['name' => 'Nom conservé', 'telephone' => null])
            ->assertOk()
            ->assertJsonPath('telephone', null);

        $this->putJson('/api/profile', ['telephone' => null])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['name']]);
    }

    public function test_profile_update_accepts_foreign_numbers_and_normalises_what_the_user_types(): void
    {
        $user = User::factory()->create();

        $attendus = [
            '00229 01 42 12 34 56' => '+229 01 42 12 34 56',
            '+229 0142123456' => '+229 01 42 12 34 56',
            '+33 6 12 34 56 78' => '+33612345678',
            '0033612345678' => '+33612345678',
            '+234 803 123 4567' => '+2348031234567',
        ];

        foreach ($attendus as $saisi => $stocke) {
            $this->actingAs($user)
                ->putJson('/api/profile', ['name' => $user->name, 'telephone' => $saisi])
                ->assertOk()
                ->assertJsonPath('telephone', $stocke);
        }
    }

    public function test_profile_update_rejects_invalid_beninese_and_malformed_international_numbers(): void
    {
        $user = User::factory()->create();

        foreach (['+229 97 00 00 00', '+229 01 25 12 34 56', '0142123456', '+33 12', '+0612345678'] as $telephone) {
            $this->actingAs($user)
                ->putJson('/api/profile', ['name' => $user->name, 'telephone' => $telephone])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('telephone');
        }
    }

    public function test_tenant_can_upload_replace_and_remove_their_profile_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

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
        $admin = User::factory()->admin()->create();

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
        $admin = User::factory()->admin()->create([
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
                'telephone' => '+229 01 97 00 00 02',
                'ville' => 'Cotonou',
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

    public function test_owner_registration_requires_a_telephone_and_a_ville(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Proprio',
            'email' => 'proprio@example.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'role' => User::ROLE_PROPRIETAIRE,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['telephone', 'ville']);

        $this->assertDatabaseMissing('users', ['email' => 'proprio@example.com']);
    }

    public function test_owner_registration_saves_and_returns_the_localisation(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Proprio',
            'email' => 'proprio@example.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'role' => User::ROLE_PROPRIETAIRE,
            'telephone' => '+229 01 97 00 00 00',
            'ville' => 'Cotonou',
            'quartier' => 'Haie Vive',
            'adresse' => 'Derrière la pharmacie',
        ])->assertCreated()
            ->assertJsonPath('email', 'proprio@example.com');

        $this->assertDatabaseHas('users', [
            'email' => 'proprio@example.com',
            'ville' => 'Cotonou',
            'quartier' => 'Haie Vive',
            'adresse' => 'Derrière la pharmacie',
        ]);
    }

    public function test_tenant_registration_neither_requires_nor_stores_a_localisation(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Locataire',
            'email' => 'locataire@example.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'role' => User::ROLE_LOCATAIRE,
            'ville' => 'Cotonou',
        ])->assertCreated()
            ->assertJsonPath('email', 'locataire@example.com');

        $this->assertDatabaseHas('users', ['email' => 'locataire@example.com', 'ville' => null]);
    }

    public function test_owner_can_update_the_localisation_but_must_keep_a_ville(): void
    {
        $owner = User::factory()->proprietaire()->create([
            'telephone' => '+229 01 97 00 00 00',
            'ville' => 'Cotonou',
        ]);

        $this->actingAs($owner)
            ->putJson('/api/profile', [
                'name' => $owner->name,
                'telephone' => '+229 01 97 00 00 00',
                'ville' => 'Porto-Novo',
                'quartier' => 'Ouando',
                'adresse' => 'Rue 12',
            ])
            ->assertOk()
            ->assertJsonPath('ville', 'Porto-Novo')
            ->assertJsonPath('quartier', 'Ouando')
            ->assertJsonPath('adresse', 'Rue 12');

        $this->actingAs($owner)
            ->putJson('/api/profile', [
                'name' => $owner->name,
                'telephone' => '+229 01 97 00 00 00',
                'ville' => '',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ville']);
    }
}
