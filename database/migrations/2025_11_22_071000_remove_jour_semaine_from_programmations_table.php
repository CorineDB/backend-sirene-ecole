<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 1. Supprimer jour_semaine car redondant avec horaires_sonneries.*.jours
     *    Les jours actifs peuvent être calculés dynamiquement à partir de horaires_sonneries
     * 2. Rendre chaine_programmee nullable car elle est générée après création
     *    via HasChaineCryptee::sauvegarderChainesCryptees()
     */
    public function up(): void
    {
        Schema::table('programmations', function (Blueprint $table) {
            $table->dropColumn('jour_semaine');
            $table->string('chaine_programmee')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('programmations', function (Blueprint $table) {
            $table->json('jour_semaine')->nullable()->after('horaires_sonneries');
            $table->string('chaine_programmee')->nullable(false)->change();
        });
    }
};
