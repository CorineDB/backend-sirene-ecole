<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interventions', function (Blueprint $table) {
            $table->string('type_intervention')->default('reparation')->after('ordre_mission_id');
            $table->integer('nombre_techniciens_requis')->nullable()->after('type_intervention');
        });
    }

    public function down(): void
    {
        Schema::table('interventions', function (Blueprint $table) {
            $table->dropColumn(['type_intervention', 'nombre_techniciens_requis']);
        });
    }
};
