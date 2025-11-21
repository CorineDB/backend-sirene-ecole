<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jours_feries', function (Blueprint $table) {
            $table->dropForeign(['calendrier_id']);
            $table->string('calendrier_id', 26)->nullable()->change();
            $table->foreign('calendrier_id')->references('id')->on('calendriers_scolaires')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('jours_feries', function (Blueprint $table) {
            $table->dropForeign(['calendrier_id']);
            $table->string('calendrier_id', 26)->nullable(false)->change();
            $table->foreign('calendrier_id')->references('id')->on('calendriers_scolaires')->onDelete('restrict');
        });
    }
};
