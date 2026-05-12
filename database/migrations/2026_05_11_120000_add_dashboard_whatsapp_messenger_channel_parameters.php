<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ROWS = [
        ['key' => 'whatsapp_dashboard_normal_enabled', 'value' => '1'],
        ['key' => 'whatsapp_dashboard_overdue_enabled', 'value' => '1'],
        ['key' => 'whatsapp_dashboard_late_enabled', 'value' => '1'],
        ['key' => 'messenger_dashboard_normal_enabled', 'value' => '1'],
        ['key' => 'messenger_dashboard_overdue_enabled', 'value' => '1'],
        ['key' => 'messenger_dashboard_late_enabled', 'value' => '1'],
        ['key' => 'medication_whatsapp_dashboard_normal_enabled', 'value' => '1'],
        ['key' => 'medication_whatsapp_dashboard_overdue_enabled', 'value' => '1'],
        ['key' => 'medication_whatsapp_dashboard_late_enabled', 'value' => '1'],
        ['key' => 'medication_messenger_dashboard_normal_enabled', 'value' => '1'],
        ['key' => 'medication_messenger_dashboard_overdue_enabled', 'value' => '1'],
        ['key' => 'medication_messenger_dashboard_late_enabled', 'value' => '1'],
    ];

    public function up(): void
    {
        foreach (self::ROWS as $row) {
            if (! DB::table('parameters')->where('key', $row['key'])->exists()) {
                DB::table('parameters')->insert([
                    'key' => $row['key'],
                    'value' => $row['value'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $keys = array_column(self::ROWS, 'key');
        DB::table('parameters')->whereIn('key', $keys)->delete();
    }
};
