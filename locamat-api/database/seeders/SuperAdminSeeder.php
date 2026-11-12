<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Compte super-administrateur de développement.
 *
 * Il n'est jamais créé en production : le mot de passe est connu de tous ceux qui lisent ce
 * dépôt. En production, utiliser `php artisan super-admin:creer <email>`.
 */
class SuperAdminSeeder extends Seeder
{
    public const EMAIL = 'superadmin@locamat.bj';

    private const PASSWORD = 'Password@123';

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('Production : créez le super-administrateur avec « php artisan super-admin:creer ».');

            return;
        }

        if (User::query()->withTrashed()->where('email', self::EMAIL)->exists()) {
            $this->command?->info(self::EMAIL.' existe déjà.');

            return;
        }

        $user = User::query()->create([
            'name' => 'Super administrateur',
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
            'is_active' => true,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->syncRoles([User::ROLE_SUPER_ADMIN]);

        $this->command?->info('Super-administrateur créé : '.self::EMAIL);
    }
}
