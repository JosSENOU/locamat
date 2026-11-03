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
        if (! Schema::hasColumn('locations', 'terminee_at')) {
            Schema::table('locations', function (Blueprint $table) {
                $table->timestamp('terminee_at')->nullable();
            });
        }

        DB::table('locations')
            ->where('statut', 'terminee')
            ->update(['terminee_at' => DB::raw('updated_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('terminee_at');
        });
    }
};
