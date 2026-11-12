<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('name')->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('name')->unique();
                $table->timestamps();
            });
        }

        $now = now();
        DB::table('roles')->insertOrIgnore([
            ['name' => 'locataire', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'proprietaire', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'admin', 'created_at' => $now, 'updated_at' => $now],
        ]);

        if (! Schema::hasTable('role_user')) {
            Schema::create('role_user', function (Blueprint $table): void {
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('role_id');
                $table->primary(['user_id', 'role_id']);
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            });
        }

        foreach (DB::table('users')->select('id', 'role')->get() as $user) {
            $roleId = DB::table('roles')->where('name', $user->role)->value('id');

            if ($roleId !== null) {
                DB::table('role_user')->insertOrIgnore([
                    'user_id' => $user->id,
                    'role_id' => $roleId,
                ]);
            }
        }

        if (! Schema::hasColumn('materiels', 'categorie_id')) {
            Schema::table('materiels', function (Blueprint $table): void {
                $table->unsignedInteger('categorie_id')->nullable();
                $table->foreign('categorie_id')->references('id')->on('categories')->restrictOnDelete();
            });
        }

        foreach (DB::table('materiels')->select('id', 'categorie')->get() as $materiel) {
            $name = trim((string) $materiel->categorie);

            if ($name === '') {
                $name = 'Autre';
            }

            DB::table('categories')->insertOrIgnore([
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $categoryId = DB::table('categories')->where('name', $name)->value('id');
            DB::table('materiels')->where('id', $materiel->id)->update(['categorie_id' => $categoryId]);
        }

        if (! Schema::hasTable('materiel_photos')) {
            Schema::create('materiel_photos', function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('materiel_id');
                $table->string('path');
                $table->unsignedSmallInteger('position')->default(0);
                $table->timestamps();
                $table->unique(['materiel_id', 'position']);
                $table->foreign('materiel_id')->references('id')->on('materiels')->cascadeOnDelete();
            });
        }

        if (Schema::hasColumn('materiels', 'photo')) {
            foreach (DB::table('materiels')->whereNotNull('photo')->get(['id', 'photo']) as $materiel) {
                DB::table('materiel_photos')->insertOrIgnore([
                    'materiel_id' => $materiel->id,
                    'path' => $materiel->photo,
                    'position' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            Schema::table('materiels', function (Blueprint $table): void {
                $table->dropColumn('photo');
            });
        }

        if (Schema::hasColumn('materiels', 'proprietaire_id') && ! Schema::hasColumn('materiels', 'user_id')) {
            Schema::table('materiels', function (Blueprint $table): void {
                $table->renameColumn('proprietaire_id', 'user_id');
            });
        }

        if (Schema::hasColumn('locations', 'locataire_id') && ! Schema::hasColumn('locations', 'user_id')) {
            Schema::table('locations', function (Blueprint $table): void {
                $table->renameColumn('locataire_id', 'user_id');
            });
        }

        if (Schema::hasColumn('materiels', 'categorie')) {
            Schema::table('materiels', function (Blueprint $table): void {
                $table->dropColumn('categorie');
            });
        }

        if (Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('role');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')->default('locataire');
        });

        $restoredUsers = [];

        foreach (DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->orderByRaw("CASE roles.name WHEN 'admin' THEN 1 WHEN 'proprietaire' THEN 2 ELSE 3 END")
            ->get(['role_user.user_id', 'roles.name']) as $userRole) {
            if (! in_array($userRole->user_id, $restoredUsers, true)) {
                DB::table('users')
                    ->where('id', $userRole->user_id)
                    ->update(['role' => $userRole->name]);
                $restoredUsers[] = $userRole->user_id;
            }
        }

        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');

        Schema::table('materiels', function (Blueprint $table): void {
            $table->string('categorie')->nullable();
        });

        foreach (DB::table('materiels')
            ->join('categories', 'categories.id', '=', 'materiels.categorie_id')
            ->get(['materiels.id', 'categories.name']) as $materiel) {
            DB::table('materiels')->where('id', $materiel->id)->update(['categorie' => $materiel->name]);
        }

        Schema::table('materiels', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('categorie_id');
        });
        Schema::dropIfExists('categories');

        if (Schema::hasColumn('materiels', 'user_id')) {
            Schema::table('materiels', function (Blueprint $table): void {
                $table->renameColumn('user_id', 'proprietaire_id');
            });
        }

        if (Schema::hasColumn('locations', 'user_id')) {
            Schema::table('locations', function (Blueprint $table): void {
                $table->renameColumn('user_id', 'locataire_id');
            });
        }

        Schema::table('materiels', function (Blueprint $table): void {
            $table->string('photo')->nullable();
        });

        foreach (DB::table('materiel_photos')->orderBy('position')->get(['materiel_id', 'path']) as $photo) {
            DB::table('materiels')
                ->where('id', $photo->materiel_id)
                ->whereNull('photo')
                ->update(['photo' => $photo->path]);
        }

        Schema::dropIfExists('materiel_photos');
    }
};
