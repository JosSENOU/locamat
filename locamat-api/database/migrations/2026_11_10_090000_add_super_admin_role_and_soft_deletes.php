<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('roles')->insertOrIgnore([
            ['name' => 'super_admin', 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::table('users', function (Blueprint $table): void {
            $table->softDeletes();
            $table->timestamp('anonymise_at')->nullable();
        });

        Schema::table('materiels', function (Blueprint $table): void {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('materiels', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['deleted_at', 'anonymise_at']);
        });

        DB::table('roles')->where('name', 'super_admin')->delete();
    }
};
