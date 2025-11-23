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
        Schema::table('sites', function (Blueprint $table) {
            if (!Schema::hasColumn('sites', 'types_etablissement')) {
                $table->json('types_etablissement')->nullable()->after('nom');
            }
            if (!Schema::hasColumn('sites', 'responsable')) {
                $table->string('responsable')->nullable()->after('types_etablissement');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['types_etablissement', 'responsable']);
        });
    }
};
