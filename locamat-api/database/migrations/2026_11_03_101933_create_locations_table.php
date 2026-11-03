<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('locations')) {
            Schema::create('locations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('materiel_id')->constrained('materiels')->restrictOnDelete();
                $table->foreignId('locataire_id')->constrained('users')->restrictOnDelete();
                $table->date('date_debut');
                $table->date('date_fin');
                $table->unsignedSmallInteger('nb_jours');
                $table->decimal('prix_total', 10, 2);
                $table->string('statut')->default('en_attente');
                $table->text('message')->nullable();
                $table->text('motif_refus')->nullable();
                $table->timestamps();

                $table->index(['materiel_id', 'statut', 'date_fin']);
            });
        } elseif (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE locations MODIFY motif_refus TEXT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
