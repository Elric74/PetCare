<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            if (!Schema::hasColumn('pets', 'is_sterilized')) {
                $table->boolean('is_sterilized')->default(false)->after('gender');
            }
            if (!Schema::hasColumn('pets', 'sterilized_at')) {
                $table->date('sterilized_at')->nullable()->after('is_sterilized');
            }
        });

        // Migrate legacy gender values to new structure
        $legacyFemelle = ['Femelle Stérilisée'];
        $legacyMale = ['Male castré'];

        DB::table('pets')->whereIn('gender', $legacyFemelle)->update(['gender' => 'Femelle', 'is_sterilized' => true]);
        DB::table('pets')->whereIn('gender', $legacyMale)->update(['gender' => 'Male', 'is_sterilized' => true]);

        DB::table('pets')->whereNull('gender')->update(['gender' => 'À déterminer']);
        DB::table('pets')->where('gender', '')->update(['gender' => 'À déterminer']);
    }

    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            if (Schema::hasColumn('pets', 'sterilized_at')) {
                $table->dropColumn('sterilized_at');
            }
            if (Schema::hasColumn('pets', 'is_sterilized')) {
                $table->dropColumn('is_sterilized');
            }
        });
    }
};
