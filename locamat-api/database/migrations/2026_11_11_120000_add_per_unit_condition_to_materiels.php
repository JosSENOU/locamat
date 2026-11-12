<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materiels', function (Blueprint $table): void {
            $table->json('etats_exemplaires')->nullable();
        });

        foreach (DB::table('materiels')->get(['id', 'etat', 'quantite']) as $materiel) {
            DB::table('materiels')
                ->where('id', $materiel->id)
                ->update([
                    'etats_exemplaires' => json_encode(
                        array_fill(0, max(1, (int) $materiel->quantite), $materiel->etat),
                        JSON_THROW_ON_ERROR,
                    ),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('materiels', function (Blueprint $table): void {
            $table->dropColumn('etats_exemplaires');
        });
    }
};
