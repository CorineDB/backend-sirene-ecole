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
        Schema::create('avis_ordres_mission', function (Blueprint $table) {
            $table->string('id', 26)->primary(); // ULID
            $table->string('ordre_mission_id', 26); // ULID
            $table->string('ecole_id', 26); // ULID
            $table->text('avis');
            $table->integer('note')->nullable(); // Note de 1 à 5
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('ordre_mission_id')->references('id')->on('ordres_mission')->onDelete('cascade');
            $table->foreign('ecole_id')->references('id')->on('ecoles')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('avis_ordres_mission');
    }
};
