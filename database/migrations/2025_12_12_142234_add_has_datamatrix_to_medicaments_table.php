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
        Schema::table('medicaments', function (Blueprint $table) {
            $table->boolean('has_datamatrix')->default(true)->after('barcode6')
                ->comment('Indicates if this medication has a DataMatrix barcode. If false, manual lot/expiry entry is required.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medicaments', function (Blueprint $table) {
            $table->dropColumn('has_datamatrix');
        });
    }
};
