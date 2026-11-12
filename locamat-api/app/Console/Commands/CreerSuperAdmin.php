<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Rules\StrongPassword;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

#[Signature('super-admin:creer {email : Adresse e-mail du compte} {--name=Super administrateur : Nom affiché} {--password= : Mot de passe (demandé de façon masquée si absent)}')]
#[Description('Crée un compte super-administrateur (aucune inscription publique ne permet d’en créer un)')]
class CreerSuperAdmin extends Command
{
    public function handle(): int
    {
        $password = (string) ($this->option('password') ?: $this->secret('Mot de passe (12 caractères minimum)'));

        $validator = Validator::make(
            [
                'name' => (string) $this->option('name'),
                'email' => (string) $this->argument('email'),
                'password' => $password,
            ],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', new StrongPassword(12)],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = User::query()->create([
            'name' => (string) $this->option('name'),
            'email' => (string) $this->argument('email'),
            'password' => $password,
            'is_active' => true,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->syncRoles([User::ROLE_SUPER_ADMIN]);

        $this->info("Super-administrateur créé : {$user->email}. Connexion sur /super-admin/connexion.");

        return self::SUCCESS;
    }
}
