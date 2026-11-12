<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BecomeOwnerRequest;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LockedAccountSupportRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\AdminAction;
use App\Models\User;
use App\Notifications\LockedAccountSupportReceived;
use App\Notifications\LockedAccountSupportRequestNotification;
use App\Notifications\ResetPasswordNotification;
use App\Rules\StrongPassword;
use App\Services\JournalAdministration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class AuthController extends Controller
{
    private const PASSWORD_RESET_CODE_MAX_ATTEMPTS = 5;

    public function __construct(private readonly JournalAdministration $journal) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $roles = $validated['roles'] ?? [$validated['role'] ?? User::ROLE_LOCATAIRE];

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'telephone' => $validated['telephone'] ?? null,
            // La localisation n'a de sens que pour un propriétaire.
            ...(in_array(User::ROLE_PROPRIETAIRE, $roles, true) ? [
                'ville' => $validated['ville'] ?? null,
                'quartier' => $validated['quartier'] ?? null,
                'adresse' => $validated['adresse'] ?? null,
            ] : []),
        ]);
        $user->syncRoles($roles);

        $this->journal->enregistrer(
            $user,
            AdminAction::INSCRIPTION,
            AdminAction::CIBLE_UTILISATEUR,
            $user->getKey(),
            $user->name,
            ['roles' => $roles],
        );

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Votre compte est créé. Consultez votre boîte e-mail pour confirmer votre adresse.',
            'email' => $user->email,
        ], 201);
    }

    public function verifyEmail(int $id, string $hash): RedirectResponse
    {
        $user = User::query()->findOrFail($id);

        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return redirect()->away(rtrim((string) config('app.frontend_url'), '/').'/verification-email?verified=1');
    }

    public function resendEmailVerification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);
        $user = User::query()->where('email', $validated['email'])->first();

        if ($user !== null && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json([
            'message' => 'Si un compte non vérifié correspond à cette adresse, un nouveau lien lui a été envoyé.',
        ]);
    }

    public function becomeOwner(BecomeOwnerRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->getKey());
            abort_unless($user->hasRole(User::ROLE_LOCATAIRE), 403, 'Seul un locataire peut devenir propriétaire.');
            abort_if($user->hasRole(User::ROLE_PROPRIETAIRE), 409, 'Ce compte est déjà propriétaire.');

            $validated = $request->validated();
            $user->update([
                'telephone' => $validated['telephone'],
                'ville' => $validated['ville'],
                'quartier' => $validated['quartier'] ?? null,
                'adresse' => $validated['adresse'] ?? null,
            ]);
            $roles = $user->roles()->pluck('name')->all();
            $roles[] = User::ROLE_PROPRIETAIRE;
            $user->syncRoles(array_values(array_unique($roles)));

            $this->journal->enregistrer(
                $user,
                AdminAction::PROFIL_PERSONNEL_MODIFIE,
                AdminAction::CIBLE_UTILISATEUR,
                $user->getKey(),
                $user->name,
                ['role_ajoute' => User::ROLE_PROPRIETAIRE],
            );

            return $user->load('roles');
        });

        return UserResource::make($user)->response();
    }

    public function login(LoginRequest $request): JsonResponse
    {
        return $this->authenticate($request, superAdmin: false);
    }

    /**
     * Point d'entrée réservé aux super-administrateurs : leur compte n'est pas accepté par
     * `login()`, et les autres comptes ne le sont pas ici (même réponse que pour un mauvais mot de passe).
     */
    public function loginSuperAdmin(LoginRequest $request): JsonResponse
    {
        return $this->authenticate($request, superAdmin: true);
    }

    private function authenticate(LoginRequest $request, bool $superAdmin): JsonResponse
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if (! $user || $user->isSuperAdmin() !== $superAdmin) {
            return response()->json([
                'message' => 'Identifiants incorrects.',
                'errors' => (object) [],
            ], 422);
        }

        return DB::transaction(function () use ($request, $user, $validated): JsonResponse {
            $user = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ($user->login_locked_at !== null && $user->login_locked_at->copy()->addHours(12)->isFuture()) {
                return $this->lockedAccountResponse($user);
            }

            if ($user->login_locked_at !== null) {
                $user->failed_login_attempts = 0;
                $user->login_locked_at = null;
            }

            $hasLegacyMd5Password = preg_match('/\A[a-f\d]{32}\z/i', $user->password) === 1;
            $passwordMatches = $hasLegacyMd5Password
                ? hash_equals(strtolower($user->password), md5($validated['password']))
                : Hash::check($validated['password'], $user->password);

            if (! $passwordMatches) {
                $user->failed_login_attempts++;

                if ($user->failed_login_attempts >= 3) {
                    $user->login_locked_at = now();
                    $user->tokens()->delete();
                    $user->save();

                    $this->journal->enregistrer(
                        null,
                        AdminAction::COMPTE_BLOQUE,
                        AdminAction::CIBLE_UTILISATEUR,
                        $user->getKey(),
                        $user->name,
                        ['tentatives' => $user->failed_login_attempts],
                        $user->getKey(),
                    );

                    return $this->lockedAccountResponse($user);
                }

                $user->save();

                return response()->json([
                    'message' => 'Identifiants incorrects.',
                    'errors' => (object) [],
                ], 422);
            }

            if ($hasLegacyMd5Password) {
                $user->password = Hash::make($validated['password']);
            }

            $user->failed_login_attempts = 0;
            $user->save();

            if (! $user->is_active) {
                return response()->json([
                    'message' => 'Ce compte est désactivé. Contactez l’assistance pour obtenir de l’aide.',
                    'errors' => (object) [],
                ], 403);
            }

            if (! $user->hasVerifiedEmail()) {
                return response()->json([
                    'message' => 'Confirmez votre adresse e-mail avant de vous connecter.',
                    'code' => 'email_not_verified',
                    'errors' => (object) [],
                ], 403);
            }

            $this->journal->enregistrer($user, AdminAction::CONNEXION, AdminAction::CIBLE_UTILISATEUR, $user->getKey(), $user->name);

            return response()->json([
                'user' => UserResource::make($user)->resolve($request),
                'token' => $user->createToken('api')->plainTextToken,
            ]);
        });
    }

    public function sendLockedAccountSupportRequest(LockedAccountSupportRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user || $user->login_locked_at === null) {
            return response()->json([
                'message' => 'Ce compte n’est pas bloqué ou le délai de blocage est terminé. Vous pouvez essayer de vous connecter.',
                'errors' => (object) [],
            ], 422);
        }

        $lockedUntil = $user->login_locked_at->copy()->addHours(12);

        if ($lockedUntil->isPast()) {
            return response()->json([
                'message' => 'Le délai de blocage est terminé. Vous pouvez essayer de vous connecter.',
                'errors' => (object) [],
            ], 422);
        }

        $admins = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN]))
            ->get();

        if ($admins->isEmpty()) {
            return response()->json([
                'message' => 'L’assistance est momentanément indisponible. Veuillez réessayer plus tard.',
                'errors' => (object) [],
            ], 503);
        }

        Notification::send($admins, new LockedAccountSupportRequestNotification(
            $user->name,
            $user->email,
            $validated['message'],
        ));
        $user->notify(new LockedAccountSupportReceived($lockedUntil));

        return response()->json([
            'message' => 'Votre demande a été envoyée à l’administrateur.',
            'locked_until' => $lockedUntil->toIso8601String(),
        ]);
    }

    private function lockedAccountResponse(User $user): JsonResponse
    {
        return response()->json([
            'message' => 'Votre compte est bloqué après trois tentatives incorrectes.',
            'locked_until' => $user->login_locked_at?->copy()->addHours(12)->toIso8601String(),
            'errors' => (object) [],
        ], 423);
    }

    public function logout(Request $request): JsonResponse
    {
        $utilisateur = $request->user();
        $utilisateur->currentAccessToken()?->delete();
        $this->journal->enregistrer($utilisateur, AdminAction::DECONNEXION, AdminAction::CIBLE_UTILISATEUR, $utilisateur->getKey(), $utilisateur->name);

        return response()->json([
            'message' => 'Déconnexion réussie.',
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $oldPhoto = $user->photo;
        $photoPath = null;

        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('profiles', 'public');

            if ($photoPath === false) {
                throw new RuntimeException('Impossible d’enregistrer la photo de profil.');
            }
        }

        $attributes = [
            'name' => $validated['name'],
            'telephone' => $validated['telephone'] ?? null,
        ];

        if ($user->hasRole(User::ROLE_PROPRIETAIRE)) {
            $attributes['ville'] = $validated['ville'];
            $attributes['quartier'] = $validated['quartier'] ?? null;
            $attributes['adresse'] = $validated['adresse'] ?? null;
        }

        if ($photoPath !== null) {
            $attributes['photo'] = $photoPath;
        } elseif (($validated['remove_photo'] ?? false) === true) {
            $attributes['photo'] = null;
        }

        $user->update($attributes);

        $this->journal->enregistrer(
            $user,
            AdminAction::PROFIL_PERSONNEL_MODIFIE,
            AdminAction::CIBLE_UTILISATEUR,
            $user->getKey(),
            $user->name,
            ['champs' => array_keys($attributes)],
        );

        if ($oldPhoto !== null && array_key_exists('photo', $attributes) && $oldPhoto !== $user->photo) {
            $disk = Storage::disk('public');

            if ($disk->exists($oldPhoto) && ! $disk->delete($oldPhoto)) {
                throw new RuntimeException('Impossible de supprimer l’ancienne photo de profil.');
            }
        }

        return UserResource::make($user->refresh())->response();
    }

    public function sendPasswordResetLink(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email']]);
        $user = User::query()->where('email', $validated['email'])->first();

        if ($user !== null) {
            $table = config('auth.passwords.users.table');
            $existingToken = DB::table($table)->where('email', $user->getEmailForPasswordReset())->first();
            $throttle = (int) config('auth.passwords.users.throttle', 60);
            $canSend = $existingToken === null
                || $existingToken->created_at === null
                || Carbon::parse($existingToken->created_at)->addSeconds($throttle)->isPast();

            if ($canSend) {
                $code = (string) random_int(100000, 999999);

                DB::table($table)->updateOrInsert(
                    ['email' => $user->getEmailForPasswordReset()],
                    ['token' => Hash::make($code), 'created_at' => now()],
                );
                $user->sendPasswordResetNotification($code);
            }
        }

        return response()->json([
            'message' => 'Si un compte correspond à cette adresse, un code de vérification lui a été envoyé.',
        ]);
    }

    public function verifyPasswordResetCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);
        $limiterKey = 'password-reset-code:'.hash('sha256', Str::lower($validated['email']).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($limiterKey, self::PASSWORD_RESET_CODE_MAX_ATTEMPTS)) {
            return response()->json([
                'message' => 'Trop de tentatives. Demandez un nouveau code dans quelques minutes.',
                'errors' => (object) [],
            ], 429);
        }

        RateLimiter::hit($limiterKey, ResetPasswordNotification::CODE_EXPIRATION_MINUTES * 60);

        $resetToken = DB::transaction(function () use ($validated): ?string {
            $user = User::query()->where('email', $validated['email'])->first();

            if ($user === null) {
                return null;
            }

            $table = config('auth.passwords.users.table');
            $email = $user->getEmailForPasswordReset();
            $storedToken = DB::table($table)->where('email', $email)->lockForUpdate()->first();

            if (
                $storedToken === null
                || $storedToken->created_at === null
                || Carbon::parse($storedToken->created_at)->addMinutes(ResetPasswordNotification::CODE_EXPIRATION_MINUTES)->isPast()
                || ! Hash::check($validated['code'], $storedToken->token)
            ) {
                return null;
            }

            $token = Str::random(64);
            DB::table($table)->where('email', $email)->update([
                'token' => Hash::make($token),
                'created_at' => now(),
            ]);

            return $token;
        });

        if ($resetToken === null) {
            return response()->json([
                'message' => 'Le code est invalide ou expiré.',
                'errors' => ['code' => ['Vérifiez le code reçu par e-mail ou demandez-en un nouveau.']],
            ], 422);
        }

        RateLimiter::clear($limiterKey);

        return response()->json(['token' => $resetToken]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'confirmed', new StrongPassword],
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();
                $user->tokens()->delete();
                $this->journal->enregistrer($user, AdminAction::MOT_DE_PASSE_REINITIALISE, AdminAction::CIBLE_UTILISATEUR, $user->getKey(), $user->name);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'La vérification a expiré ou est invalide. Demandez un nouveau code.',
                'errors' => ['email' => ['Demandez un nouveau code de vérification.']],
            ], 422);
        }

        return response()->json(['message' => 'Votre mot de passe a été réinitialisé.']);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Le mot de passe actuel est incorrect.',
                'errors' => ['current_password' => ['Le mot de passe actuel est incorrect.']],
            ], 422);
        }

        $user->update(['password' => $validated['password']]);
        $this->journal->enregistrer($user, AdminAction::MOT_DE_PASSE_MODIFIE, AdminAction::CIBLE_UTILISATEUR, $user->getKey(), $user->name);

        $currentToken = $user->currentAccessToken();
        if ($currentToken !== null && method_exists($currentToken, 'getKey')) {
            $user->tokens()->whereKeyNot($currentToken->getKey())->delete();
        }

        return response()->json(['message' => 'Votre mot de passe a été modifié.']);
    }
}
