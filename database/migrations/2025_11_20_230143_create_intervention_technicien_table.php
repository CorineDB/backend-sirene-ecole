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
        Schema::create('intervention_technicien', function (Blueprint $table) {
            $table->string('id', 26)->primary(); // ULID
            $table->string('intervention_id', 26);
            $table->string('technicien_id', 26);
            $table->timestamp('date_assignation')->useCurrent();
            $table->string('role')->nullable(); // Ex: "chef d'équipe", "assistant", etc.
            $table->text('notes')->nullable(); // Notes spécifiques du technicien
            $table->timestamps();

            $table->foreign('intervention_id')->references('id')->on('interventions')->onDelete('cascade');
            $table->foreign('technicien_id')->references('id')->on('techniciens')->onDelete('restrict');

            // Un technicien ne peut être assigné qu'une fois à la même intervention
            $table->unique(['intervention_id', 'technicien_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('intervention_technicien');
    }
};
