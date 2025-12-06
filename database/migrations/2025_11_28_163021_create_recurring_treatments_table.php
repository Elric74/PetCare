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
        Schema::create('recurring_treatments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('periodicity'); // '1m', '3m', '6m', '1y', etc.
            $table->foreignId('species_id')->nullable()->constrained('species')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_treatments');
    }
};
