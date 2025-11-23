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
        Schema::table('pannes', function (Blueprint $table) {
            //
            if (!Schema::hasColumn('pannes', 'objet')) {
                $table->text('objet')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pannes', function (Blueprint $table) {
            //
            if (Schema::hasColumn('pannes', 'objet')) {
            $table->dropColumn(['objet']);
            }
        });
    }
};
