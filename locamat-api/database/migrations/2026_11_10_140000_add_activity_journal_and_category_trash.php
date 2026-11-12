<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_actions', function (Blueprint $table): void {
            $table->unsignedBigInteger('concerne_user_id')->nullable()->after('cible_libelle');
            $table->index('concerne_user_id');
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });

        Schema::table('admin_actions', function (Blueprint $table): void {
            $table->dropIndex(['concerne_user_id']);
            $table->dropColumn('concerne_user_id');
        });
    }
};
