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
        Schema::create('avis_rapports', function (Blueprint $table) {
            $table->string('id', 26)->primary(); // ULID
            $table->string('rapport_intervention_id', 26);
            $table->string('admin_id', 26); // Admin qui évalue
            $table->integer('note')->unsigned(); // 1 à 5
            $table->text('review')->nullable(); // Commentaire détaillé
            $table->enum('type_evaluation', ['completude', 'clarte', 'precision', 'conformite'])->default('completude');
            $table->boolean('approuve')->default(true); // Rapport approuvé?
            $table->text('points_forts')->nullable(); // Points positifs
            $table->text('points_amelioration')->nullable(); // Points à améliorer
            $table->timestamp('date_evaluation')->useCurrent();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('rapport_intervention_id')->references('id')->on('rapports_intervention')->onDelete('cascade');
            $table->foreign('admin_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('avis_rapports');
    }
};
