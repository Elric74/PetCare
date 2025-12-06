<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Step 1: Rename gtin to barcode1 (GTIN DataMatrix)
        if (Schema::hasColumn('medicaments', 'gtin') && !Schema::hasColumn('medicaments', 'barcode1')) {
            Schema::table('medicaments', function (Blueprint $table) {
                $table->renameColumn('gtin', 'barcode1');
            });
        }
        
        // Step 2: Rename cnk to barcode2 (CNK Belgian code)
        if (Schema::hasColumn('medicaments', 'cnk') && !Schema::hasColumn('medicaments', 'barcode2')) {
            Schema::table('medicaments', function (Blueprint $table) {
                $table->renameColumn('cnk', 'barcode2');
            });
        }
        
        // Step 3: Add 4 additional barcode columns
        Schema::table('medicaments', function (Blueprint $table) {
            if (!Schema::hasColumn('medicaments', 'barcode3')) {
                $table->string('barcode3')->nullable()->after('barcode2')->comment('Code alternatif 1');
            }
            if (!Schema::hasColumn('medicaments', 'barcode4')) {
                $table->string('barcode4')->nullable()->after('barcode3')->comment('Code alternatif 2');
            }
            if (!Schema::hasColumn('medicaments', 'barcode5')) {
                $table->string('barcode5')->nullable()->after('barcode4')->comment('Code alternatif 3');
            }
            if (!Schema::hasColumn('medicaments', 'barcode6')) {
                $table->string('barcode6')->nullable()->after('barcode5')->comment('Code alternatif 4');
            }
        });
        
        // Step 4: Create unknown_barcodes table for unmatched codes
        if (!Schema::hasTable('unknown_barcodes')) {
            Schema::create('unknown_barcodes', function (Blueprint $table) {
                $table->id();
                $table->string('gtin')->unique()->comment('Code-barres DataMatrix GTIN non reconnu');
                $table->string('commercial_name')->comment('Nom commercial saisi manuellement');
                $table->string('dosage')->nullable()->comment('Dosage ou complément du nom');
                $table->timestamps();
            });
        }
        
        // Step 5: Add barcode_type to inventaire_medoc
        Schema::table('inventaire_medoc', function (Blueprint $table) {
            // Rename gtin to barcode if not already done
            if (Schema::hasColumn('inventaire_medoc', 'gtin') && !Schema::hasColumn('inventaire_medoc', 'barcode')) {
                $table->renameColumn('gtin', 'barcode');
            }
        });
        
        Schema::table('inventaire_medoc', function (Blueprint $table) {
            // Add barcode_type field
            if (!Schema::hasColumn('inventaire_medoc', 'barcode_type')) {
                $table->string('barcode_type')->nullable()->after('barcode')->comment('barcode1/2/3/4/5/6 - which code matched');
            }
            // Drop cnk if still exists
            if (Schema::hasColumn('inventaire_medoc', 'cnk')) {
                $table->dropColumn('cnk');
            }
        });
    }

    public function down(): void
    {
        // Revert all changes
        Schema::table('medicaments', function (Blueprint $table) {
            if (Schema::hasColumn('medicaments', 'barcode1')) {
                $table->renameColumn('barcode1', 'gtin');
            }
            if (Schema::hasColumn('medicaments', 'barcode2')) {
                $table->renameColumn('barcode2', 'cnk');
            }
            $table->dropColumn(['barcode3', 'barcode4', 'barcode5', 'barcode6']);
        });
        
        Schema::dropIfExists('unknown_barcodes');
        
        Schema::table('inventaire_medoc', function (Blueprint $table) {
            if (Schema::hasColumn('inventaire_medoc', 'barcode')) {
                $table->renameColumn('barcode', 'gtin');
            }
            if (Schema::hasColumn('inventaire_medoc', 'barcode_type')) {
                $table->dropColumn('barcode_type');
            }
        });
    }
};
