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
        // 1. Ajouter est_prive à la table ecoles
        Schema::table('ecoles', function (Blueprint $table) {
            $table->boolean('est_prive')->default(false)->after('types_etablissement');
        });

        // 2. Ajouter types_etablissement et responsable à la table sites
        Schema::table('sites', function (Blueprint $table) {
            $table->json('types_etablissement')->nullable()->after('nom');
            $table->string('responsable')->nullable()->after('types_etablissement');
        });

        // 3. Supprimer types_etablissement de la table ecoles
        Schema::table('ecoles', function (Blueprint $table) {
            $table->dropColumn('types_etablissement');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restaurer types_etablissement dans ecoles
        Schema::table('ecoles', function (Blueprint $table) {
            $table->json('types_etablissement')->after('email_contact');
        });

        // Supprimer les colonnes de sites
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['types_etablissement', 'responsable']);
        });

        // Supprimer est_prive de ecoles
        Schema::table('ecoles', function (Blueprint $table) {
            $table->dropColumn('est_prive');
        });
    }
};
