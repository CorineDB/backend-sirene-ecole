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
        Schema::table('techniciens', function (Blueprint $table) {
            $table->string('ville_id', 26)->nullable()->after('date_embauche');
            $table->foreign('ville_id')->references('id')->on('villes')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('techniciens', function (Blueprint $table) {
            $table->dropForeign(['ville_id']);
            $table->dropColumn('ville_id');
        });
    }
};
