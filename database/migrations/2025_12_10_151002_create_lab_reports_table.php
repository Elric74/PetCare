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
        Schema::create('lab_reports', function (Blueprint $table) {
            $table->id();
            $table->string('zoolyx_report_id')->unique();
            $table->foreignId('pet_id')->nullable()->constrained()->onDelete('set null');
            $table->string('pet_name');
            $table->string('pet_species')->nullable();
            $table->string('owner_first_name')->nullable();
            $table->string('owner_last_name')->nullable();
            $table->date('reception_date');
            $table->date('updated_date')->nullable();
            $table->string('pdf_path');
            $table->timestamps();
            
            $table->index('reception_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_reports');
    }
};
