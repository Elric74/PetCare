<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('sms_log', function (Blueprint $table) {
            $table->boolean('sms_sent_as_normal')->default(false)->after('vaccination_id');
            $table->boolean('sms_sent_as_overdue')->default(false)->after('sms_sent_as_normal');
            $table->boolean('sms_sent_as_late')->default(false)->after('sms_sent_as_overdue');
        });
    }

    public function down()
    {
        Schema::table('sms_log', function (Blueprint $table) {
            $table->dropColumn(['sms_sent_as_normal', 'sms_sent_as_overdue', 'sms_sent_as_late']);
        });
    }
};
