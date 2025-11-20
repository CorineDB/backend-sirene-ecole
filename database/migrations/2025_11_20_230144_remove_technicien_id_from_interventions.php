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
        Schema::table('interventions', function (Blueprint $table) {
            // Supprimer la clé étrangère
            $table->dropForeign(['technicien_id']);

            // Supprimer la colonne technicien_id
            $table->dropColumn('technicien_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('interventions', function (Blueprint $table) {
            // Restaurer la colonne
            $table->string('technicien_id', 26)->nullable()->after('panne_id');

            // Restaurer la clé étrangère
            $table->foreign('technicien_id')->references('id')->on('techniciens')->onDelete('restrict');
        });
    }
};
