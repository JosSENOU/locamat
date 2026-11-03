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
        if (! Schema::hasTable('users') && Schema::hasTable('utilisateurs')) {
            Schema::rename('utilisateurs', 'users');
        }

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->rememberToken();
                $table->timestamps();
            });
        } else {
            if (Schema::hasColumn('users', 'nom') && ! Schema::hasColumn('users', 'name')) {
                Schema::table('users', fn (Blueprint $table) => $table->renameColumn('nom', 'name'));
            }

            Schema::table('users', function (Blueprint $table): void {
                if (! Schema::hasColumn('users', 'email_verified_at')) {
                    $table->timestamp('email_verified_at')->nullable();
                }

                if (! Schema::hasColumn('users', 'remember_token')) {
                    $table->rememberToken();
                }
            });

            if (DB::connection()->getDriverName() === 'mysql') {
                $modifications = ['MODIFY name VARCHAR(255) NOT NULL', 'MODIFY email VARCHAR(255) NOT NULL'];

                if (Schema::hasColumn('users', 'telephone')) {
                    $modifications[] = 'MODIFY telephone VARCHAR(30) NULL';
                }

                DB::statement('ALTER TABLE users '.implode(', ', $modifications));
            }
        }

        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
