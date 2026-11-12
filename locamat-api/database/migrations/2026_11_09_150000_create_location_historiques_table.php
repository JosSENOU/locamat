<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reste éventuel d'une exécution interrompue (cette migration n'est alors pas enregistrée).
        Schema::dropIfExists('location_historiques');

        Schema::create('location_historiques', function (Blueprint $table): void {
            $table->id();
            $this->colonneCle($table, 'location_id', 'locations');
            $this->colonneCle($table, 'user_id', 'users')->nullable();
            $table->string('evenement', 40);
            $table->string('statut', 20);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('location_id')->references('id')->on('locations')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['location_id', 'id']);
        });

        $this->reprendreLesLocationsExistantes();
    }

    public function down(): void
    {
        Schema::dropIfExists('location_historiques');
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

    /**
     * Les locations créées avant cette migration reçoivent un historique minimal :
     * la création de la demande, puis son statut actuel.
     */
    private function reprendreLesLocationsExistantes(): void
    {
        $evenements = [
            'confirmee' => 'confirmation',
            'refusee' => 'refus',
            'annulee' => 'annulation',
            'terminee' => 'terminee',
        ];

        DB::table('locations')->orderBy('id')->chunkById(200, function ($locations) use ($evenements): void {
            $lignes = [];

            foreach ($locations as $location) {
                $lignes[] = [
                    'location_id' => $location->id,
                    'user_id' => $location->user_id,
                    'evenement' => 'creation',
                    'statut' => 'en_attente',
                    'note' => null,
                    'created_at' => $location->created_at,
                ];

                if (isset($evenements[$location->statut])) {
                    $lignes[] = [
                        'location_id' => $location->id,
                        'user_id' => null,
                        'evenement' => $evenements[$location->statut],
                        'statut' => $location->statut,
                        'note' => $location->statut === 'refusee' ? $location->motif_refus : null,
                        'created_at' => $location->statut === 'terminee'
                            ? ($location->terminee_at ?? $location->updated_at)
                            : $location->updated_at,
                    ];
                }
            }

            DB::table('location_historiques')->insert($lignes);
        });
    }
};
