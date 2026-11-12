<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reste éventuel d'une exécution interrompue (cette migration n'est alors pas enregistrée).
        Schema::dropIfExists('reclamations');

        Schema::create('reclamations', function (Blueprint $table): void {
            $table->id();
            $this->colonneCle($table, 'location_id', 'locations');
            $this->colonneCle($table, 'locataire_id', 'users');
            $this->colonneCle($table, 'proprietaire_id', 'users');
            $table->string('motif', 60);
            $table->text('description');
            $table->string('statut', 20)->default('nouvelle');
            $table->text('note_admin')->nullable();
            $table->timestamp('traitee_at')->nullable();
            $table->timestamps();

            $table->foreign('location_id')->references('id')->on('locations')->cascadeOnDelete();
            $table->foreign('locataire_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('proprietaire_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['statut', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reclamations');
    }

    /**
     * Reprend le type de la clé primaire référencée : selon l'historique de la base,
     * `id` est un `int unsigned` (anciennes bases) ou un `bigint unsigned`.
     */
    private function colonneCle(Blueprint $table, string $colonne, string $tableReferencee): ColumnDefinition
    {
        $type = collect(Schema::getColumns($tableReferencee))->firstWhere('name', 'id')['type_name'] ?? 'bigint';

        return in_array($type, ['int', 'integer', 'mediumint', 'smallint'], true)
            ? $table->unsignedInteger($colonne)
            : $table->unsignedBigInteger($colonne);
    }
};
