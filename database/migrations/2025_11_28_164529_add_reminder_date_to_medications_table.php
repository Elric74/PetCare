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
        Schema::table('medications', function (Blueprint $table) {
            if (!Schema::hasColumn('medications', 'reminder_date')) {
                $table->date('reminder_date')->nullable()->after('administered_at');
            }
            if (!Schema::hasColumn('medications', 'frequency')) {
                $table->string('frequency')->nullable()->after('dosage');
            }
            if (!Schema::hasColumn('medications', 'administering_veterinarian')) {
                $table->string('administering_veterinarian')->nullable()->after('frequency');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medications', function (Blueprint $table) {
            $drops = [];
            if (Schema::hasColumn('medications', 'reminder_date')) $drops[] = 'reminder_date';
            if (Schema::hasColumn('medications', 'frequency')) $drops[] = 'frequency';
            if (Schema::hasColumn('medications', 'administering_veterinarian')) $drops[] = 'administering_veterinarian';
            if (!empty($drops)) {
                $table->dropColumn($drops);
            }
        });
    }
};
