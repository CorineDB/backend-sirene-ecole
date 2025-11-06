<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Suppression des champs redondants et inutiles :
     * - horaire_json : redondant avec horaires_sonneries
     * - horaire_debut : calculable (min de horaires_sonneries)
     * - horaire_fin : calculable (max de horaires_sonneries)
     * - vacances : redondant avec calendrier_scolaire
     * - types_etablissement : l'école a déjà son type
     * - date_creation : redondant avec created_at
     */
    public function up(): void
    {
        Schema::table('programmations', function (Blueprint $table) {
            // Supprimer les champs redondants/inutiles
            $table->dropColumn([
                'horaire_json',
                'horaire_debut',
                'horaire_fin',
                'vacances',
                'types_etablissement',
                'date_creation',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('programmations', function (Blueprint $table) {
            // Restaurer les champs (pour rollback)
            $table->json('horaire_json')->nullable()->after('nom_programmation');
            $table->time('horaire_debut')->nullable()->after('horaires_sonneries');
            $table->time('horaire_fin')->nullable()->after('horaire_debut');
            $table->json('vacances')->nullable()->after('jours_feries_inclus');
            $table->json('types_etablissement')->nullable()->after('vacances');
            $table->timestamp('date_creation')->useCurrent()->after('actif');
        });
    }
};
