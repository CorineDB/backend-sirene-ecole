<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Rendre chaine_programmee nullable car elle est générée après la création
     * via la méthode sauvegarderChainesCryptees() du trait HasChaineCryptee
     */
    public function up(): void
    {
        Schema::table('programmations', function (Blueprint $table) {
            $table->string('chaine_programmee')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('programmations', function (Blueprint $table) {
            $table->string('chaine_programmee')->nullable(false)->change();
        });
    }
};
