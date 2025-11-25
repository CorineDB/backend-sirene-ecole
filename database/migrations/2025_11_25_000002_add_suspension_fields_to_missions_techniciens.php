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
        Schema::table('missions_techniciens', function (Blueprint $table) {
            $table->boolean('is_suspended')->default(false)->after('statut_candidature');
            $table->text('motif_suspension')->nullable()->after('is_suspended');
            $table->timestamp('date_suspension')->nullable()->after('motif_suspension');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('missions_techniciens', function (Blueprint $table) {
            $table->dropColumn(['is_suspended', 'motif_suspension', 'date_suspension']);
        });
    }
};
