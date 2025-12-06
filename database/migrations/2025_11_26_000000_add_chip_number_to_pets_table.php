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
        Schema::table('pets', function (Blueprint $table) {
            $table->string('chip_number')->nullable()->unique()->after('photo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            // Drop unique index first if it exists, then drop the column
            if (Schema::hasColumn('pets', 'chip_number')) {
                $table->dropUnique(['chip_number']);
                $table->dropColumn('chip_number');
            }
        });
    }
};
