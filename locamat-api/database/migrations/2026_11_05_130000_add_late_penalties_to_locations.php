<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table): void {
            $table->unsignedInteger('penalite_jours')->nullable();
            $table->decimal('penalite_montant', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table): void {
            $table->dropColumn(['penalite_jours', 'penalite_montant']);
        });
    }
};
