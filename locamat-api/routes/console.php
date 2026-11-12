<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Les rappels de location (remise, retour, retard) ne passent plus par le planificateur : ils sont
// créés dans l'application, une seule fois, à l'ouverture (NotificationController).
// La commande `locations:envoyer-rappels` reste disponible pour un envoi manuel par e-mail.

Schedule::command('corbeille:purger')
    ->dailyAt('02:00')
    ->withoutOverlapping();
