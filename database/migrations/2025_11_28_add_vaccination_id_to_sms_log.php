<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('sms_log', function (Blueprint $table) {
            $table->unsignedBigInteger('vaccination_id')->nullable()->after('pet_id');
            $table->foreign('vaccination_id')->references('id')->on('vaccinations')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('sms_log', function (Blueprint $table) {
            $table->dropForeign(['vaccination_id']);
            $table->dropColumn('vaccination_id');
        });
    }
};
