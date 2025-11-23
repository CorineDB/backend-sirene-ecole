<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Ajouter les champs de planification à la table interventions
        Schema::table('interventions', function (Blueprint $table) {
            $table->text('instructions')->nullable()->after('observations');
            $table->string('lieu_rdv')->nullable()->after('instructions');
            $table->time('heure_rdv')->nullable()->after('lieu_rdv');
        });

        // 2. Supprimer les champs de ordres_mission (ils étaient au mauvais endroit)
        Schema::table('ordres_mission', function (Blueprint $table) {
            $table->dropColumn([
                'date_intervention_prevue',
                'instructions',
                'lieu_rdv',
                'heure_rdv'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restaurer dans ordres_mission
        Schema::table('ordres_mission', function (Blueprint $table) {
            $table->timestamp('date_intervention_prevue')->nullable()->after('date_fin_candidature');
            $table->text('instructions')->nullable()->after('commentaire');
            $table->string('lieu_rdv')->nullable()->after('instructions');
            $table->time('heure_rdv')->nullable()->after('lieu_rdv');
        });

        // Supprimer de interventions
        Schema::table('interventions', function (Blueprint $table) {
            $table->dropColumn(['instructions', 'lieu_rdv', 'heure_rdv']);
        });
    }
};
