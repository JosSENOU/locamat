<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_actions', function (Blueprint $table): void {
            $table->id();
            $this->colonneCle($table, 'user_id', 'users')->nullable();
            $table->string('acteur_nom');
            $table->string('acteur_role', 30)->nullable();
            $table->string('action', 40);
            $table->string('cible_type', 30);
            $table->unsignedBigInteger('cible_id')->nullable();
            $table->string('cible_libelle')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_actions');
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
