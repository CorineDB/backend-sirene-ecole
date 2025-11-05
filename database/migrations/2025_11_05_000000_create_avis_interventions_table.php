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
        Schema::create('avis_interventions', function (Blueprint $table) {
            $table->string('id', 26)->primary(); // ULID
            $table->string('intervention_id', 26);
            $table->string('ecole_id', 26); // Qui donne l'avis
            $table->string('auteur_id', 26)->nullable(); // User de l'école qui a soumis l'avis
            $table->integer('note')->unsigned(); // 1 à 5
            $table->text('commentaire')->nullable();
            $table->enum('type_avis', ['satisfaction', 'qualite_travail', 'professionnalisme', 'delai', 'proprete'])->default('satisfaction');
            $table->boolean('recommande')->default(true); // Recommande ce technicien?
            $table->timestamp('date_avis')->useCurrent();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('intervention_id')->references('id')->on('interventions')->onDelete('cascade');
            $table->foreign('ecole_id')->references('id')->on('ecoles')->onDelete('cascade');
            $table->foreign('auteur_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('avis_interventions');
    }
};
