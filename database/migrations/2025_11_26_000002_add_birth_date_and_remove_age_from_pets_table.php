<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            $table->date('birth_date')->nullable()->after('age');
        });

        // If there are existing age values, convert them to a birth_date roughly by subtracting years
        // This sets birth_date = CURDATE() - INTERVAL age YEAR for records with age not null
        DB::statement("UPDATE pets SET birth_date = DATE_SUB(CURDATE(), INTERVAL age YEAR) WHERE age IS NOT NULL");

        Schema::table('pets', function (Blueprint $table) {
            if (Schema::hasColumn('pets', 'age')) {
                $table->dropColumn('age');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            $table->integer('age')->nullable()->after('breed_id');
        });

        // Optionally convert birth_date back to age (years)
        DB::statement("UPDATE pets SET age = TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) WHERE birth_date IS NOT NULL");

        Schema::table('pets', function (Blueprint $table) {
            if (Schema::hasColumn('pets', 'birth_date')) {
                $table->dropColumn('birth_date');
            }
        });
    }
};
