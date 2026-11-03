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
        if (! Schema::hasTable('materiels')) {
            Schema::create('materiels', function (Blueprint $table) {
                $table->id();
                $table->foreignId('proprietaire_id')->constrained('users')->restrictOnDelete();
                $table->string('nom');
                $table->string('categorie');
                $table->text('description')->nullable();
                $table->string('photo')->nullable();
                $table->decimal('prix_journalier', 10, 2);
                $table->string('etat');
                $table->boolean('disponible')->default(true);
                $table->timestamps();
            });
        } else {
            if (Schema::hasColumn('materiels', 'prix_jour') && ! Schema::hasColumn('materiels', 'prix_journalier')) {
                Schema::table('materiels', fn (Blueprint $table) => $table->renameColumn('prix_jour', 'prix_journalier'));
            }

            if (DB::connection()->getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE materiels MODIFY nom VARCHAR(255) NOT NULL, MODIFY etat VARCHAR(20) NOT NULL DEFAULT 'bon'");
                DB::table('materiels')->where('etat', 'usage')->update(['etat' => 'usé']);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materiels');
    }
};
