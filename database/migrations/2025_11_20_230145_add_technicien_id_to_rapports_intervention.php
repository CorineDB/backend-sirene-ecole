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
        Schema::table('rapports_intervention', function (Blueprint $table) {
            // Ajouter technicien_id nullable (null = rapport collectif, sinon = rapport individuel)
            $table->string('technicien_id', 26)->nullable()->after('intervention_id');

            // Ajouter clé étrangère
            $table->foreign('technicien_id')->references('id')->on('techniciens')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rapports_intervention', function (Blueprint $table) {
            // Supprimer la clé étrangère
            $table->dropForeign(['technicien_id']);

            // Supprimer la colonne
            $table->dropColumn('technicien_id');
        });
    }
};
