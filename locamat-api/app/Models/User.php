<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'telephone', 'ville', 'quartier', 'adresse', 'is_active', 'photo', 'failed_login_attempts', 'login_locked_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmailContract
{
    public const ROLE_LOCATAIRE = 'locataire';

    public const ROLE_PROPRIETAIRE = 'proprietaire';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_SUPER_ADMIN = 'super_admin';

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, MustVerifyEmailTrait, Notifiable, SoftDeletes;

    public function hasRole(string ...$roles): bool
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->contains(fn (Role $role): bool => in_array($role->name, $roles, true));
        }

        return $this->roles()->whereIn('name', $roles)->exists();
    }

    /**
     * Actions de ce compte dans le journal de la plateforme.
     */
    public function journal(): HasMany
    {
        return $this->hasMany(AdminAction::class, 'user_id');
    }

    /**
     * Administrateur ou super-administrateur : les comptes de gestion de la plateforme.
     */
    public function isAdministrator(): bool
    {
        return $this->hasRole(self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN);
    }

    public function primaryRole(): ?string
    {
        $roleNames = $this->roles->pluck('name');

        foreach ([self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_PROPRIETAIRE, self::ROLE_LOCATAIRE] as $role) {
            if ($roleNames->contains($role)) {
                return $role;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $roles
     */
    public function syncRoles(array $roles): void
    {
        $roleIds = Role::query()->whereIn('name', $roles)->pluck('id');

        $this->roles()->sync($roleIds);
        $this->unsetRelation('roles');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->orderBy('roles.id');
    }

    public function materiels(): HasMany
    {
        return $this->hasMany(Materiel::class, 'user_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class, 'user_id');
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'failed_login_attempts' => 'integer',
            'login_locked_at' => 'datetime',
            'anonymise_at' => 'datetime',
        ];
    }
}
