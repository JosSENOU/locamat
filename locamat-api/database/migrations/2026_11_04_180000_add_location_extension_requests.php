<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table): void {
            $table->date('prolongation_date_fin')->nullable();
            $table->decimal('prolongation_montant', 10, 2)->nullable();
            $table->string('prolongation_statut', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table): void {
            $table->dropColumn([
                'prolongation_date_fin',
                'prolongation_montant',
                'prolongation_statut',
            ]);
        });
    }
};
