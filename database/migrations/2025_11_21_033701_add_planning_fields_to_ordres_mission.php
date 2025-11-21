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
        Schema::table('ordres_mission', function (Blueprint $table) {
            // Date prévue pour l'intervention (peut être définie avant/pendant les candidatures)
            $table->timestamp('date_intervention_prevue')->nullable()->after('date_fin_candidature');

            // Instructions/briefing pour les techniciens
            $table->text('instructions')->nullable()->after('commentaire');

            // Lieu de rendez-vous (si différent du site)
            $table->string('lieu_rdv')->nullable()->after('instructions');

            // Heure de rendez-vous
            $table->time('heure_rdv')->nullable()->after('lieu_rdv');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ordres_mission', function (Blueprint $table) {
            $table->dropColumn([
                'date_intervention_prevue',
                'instructions',
                'lieu_rdv',
                'heure_rdv'
            ]);
        });
    }
};
