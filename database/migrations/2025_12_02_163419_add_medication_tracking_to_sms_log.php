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
        Schema::table('sms_log', function (Blueprint $table) {
            $table->unsignedBigInteger('medication_id')->nullable()->after('vaccination_id');
            $table->boolean('medication_sms_sent_as_normal')->default(false)->after('sms_sent_as_late');
            $table->boolean('medication_sms_sent_as_overdue')->default(false)->after('medication_sms_sent_as_normal');
            $table->boolean('medication_sms_sent_as_late')->default(false)->after('medication_sms_sent_as_overdue');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sms_log', function (Blueprint $table) {
            $table->dropColumn(['medication_id', 'medication_sms_sent_as_normal', 'medication_sms_sent_as_overdue', 'medication_sms_sent_as_late']);
        });
    }
};
