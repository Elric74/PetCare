<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inventaire_medoc', function (Blueprint $table) {
            $table->string('gtin')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('inventaire_medoc', function (Blueprint $table) {
            $table->string('gtin')->nullable(false)->change();
        });
    }
};
