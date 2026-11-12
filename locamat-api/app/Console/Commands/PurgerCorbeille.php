<?php

namespace App\Console\Commands;

use App\Services\CorbeilleService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('corbeille:purger')]
#[Description('Supprime définitivement les comptes et matériels restés dans la corbeille au-delà de la durée de conservation')]
class PurgerCorbeille extends Command
{
    public function handle(CorbeilleService $corbeille): int
    {
        $resume = $corbeille->purgerExpires();

        $this->info(sprintf(
            'Corbeille purgée : %d compte(s) supprimé(s), %d compte(s) anonymisé(s), %d matériel(s) et %d catégorie(s) supprimé(s).',
            $resume['utilisateurs_supprimes'],
            $resume['utilisateurs_anonymises'],
            $resume['materiels_supprimes'],
            $resume['categories_supprimees'],
        ));

        return self::SUCCESS;
    }
}
