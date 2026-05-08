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
        Schema::table('inventaire_medoc', function (Blueprint $table) {
            // Add price columns for years 2025-2030
            $table->decimal('prix_2025', 10, 2)->nullable()->after('dosage')->comment('Prix unitaire pour l\'année 2025');
            $table->decimal('prix_2026', 10, 2)->nullable()->after('prix_2025')->comment('Prix unitaire pour l\'année 2026');
            $table->decimal('prix_2027', 10, 2)->nullable()->after('prix_2026')->comment('Prix unitaire pour l\'année 2027');
            $table->decimal('prix_2028', 10, 2)->nullable()->after('prix_2027')->comment('Prix unitaire pour l\'année 2028');
            $table->decimal('prix_2029', 10, 2)->nullable()->after('prix_2028')->comment('Prix unitaire pour l\'année 2029');
            $table->decimal('prix_2030', 10, 2)->nullable()->after('prix_2029')->comment('Prix unitaire pour l\'année 2030');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventaire_medoc', function (Blueprint $table) {
            $table->dropColumn([
                'prix_2025',
                'prix_2026',
                'prix_2027',
                'prix_2028',
                'prix_2029',
                'prix_2030'
            ]);
        });
    }
};
