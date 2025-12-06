<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('sms_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pet_id');
            $table->unsignedBigInteger('vaccination_id')->nullable();
            $table->text('message');
            $table->timestamp('sent_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->foreign('pet_id')->references('id')->on('pets')->onDelete('cascade');
            $table->foreign('vaccination_id')->references('id')->on('vaccinations')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('sms_log');
    }
};