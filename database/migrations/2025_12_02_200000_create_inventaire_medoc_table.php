<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventaire_medoc', function (Blueprint $table) {
            $table->id();
            $table->string('gtin')->index();
            $table->string('serial_number')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('lot_number')->nullable();
            $table->text('raw')->nullable();
            $table->timestamp('scanned_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventaire_medoc');
    }
};
