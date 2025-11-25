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
            $table->boolean('confirme_par_ecole')->default(false)->after('statut');
            $table->timestamp('date_confirmation_ecole')->nullable()->after('confirme_par_ecole');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('interventions', function (Blueprint $table) {
            $table->dropColumn(['confirme_par_ecole', 'date_confirmation_ecole']);
        });
    }
};
