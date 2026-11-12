<?php

namespace App\Console\Commands;

use App\Services\LocationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('locations:envoyer-rappels {--jours=3 : Nombre de jours avant la remise ou le retour du matériel}')]
#[Description('Envoie les rappels de remise, de retour et de retard des locations confirmées')]
class EnvoyerRappelsLocations extends Command
{
    public function handle(LocationService $locationService): int
    {
        $joursAvant = (int) $this->option('jours');

        if ($joursAvant < 1) {
            $this->error('Le nombre de jours doit être supérieur ou égal à 1.');

            return self::FAILURE;
        }

        $envoyes = $locationService->envoyerRappels($joursAvant);

        $this->info($envoyes.' rappel(s) de location envoyé(s).');

        return self::SUCCESS;
    }
}
