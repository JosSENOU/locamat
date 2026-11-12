<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Localisation d'un propriétaire : où le matériel peut être récupéré.
     *
     * - ville / quartier : visibles publiquement sur la fiche du matériel ;
     * - adresse (repère précis) : partagée avec le locataire une fois la location confirmée.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'ville')) {
                $table->string('ville', 100)->nullable();
            }

            if (! Schema::hasColumn('users', 'quartier')) {
                $table->string('quartier', 150)->nullable();
            }

            if (! Schema::hasColumn('users', 'adresse')) {
                $table->string('adresse', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['ville', 'quartier', 'adresse']);
        });
    }
};
