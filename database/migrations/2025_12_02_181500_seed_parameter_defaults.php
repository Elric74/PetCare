<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $defaults = [
            // Vaccine SMS templates
            ['key' => 'sms_message_normal', 'value' => "Ici votre vétérinaire, votre {species} {pet_name} a besoin d'avoir son rappel de vaccin {vaccine_name} avant le {date}. Merci de prendre rdv au plus vite."],
            ['key' => 'sms_message_overdue', 'value' => 'Rappel: Votre {species} {pet_name} est en retard pour son vaccin {vaccine_name}. Veuillez prendre rendez-vous dès que possible.'],
            ['key' => 'sms_message_late', 'value' => "URGENT: Votre {species} {pet_name} n'a pas eu son vaccin {vaccine_name} depuis très longtemps. Veuillez contacter votre vétérinaire immédiatement."],
            // Treatment SMS templates
            ['key' => 'medication_sms_message_normal', 'value' => "Ici votre vétérinaire, votre {species} {pet_name} a un traitement {treatment_name} à réaliser avant le {date}. Merci de prendre rdv au plus vite."],
            ['key' => 'medication_sms_message_overdue', 'value' => 'Rappel: Votre {species} {pet_name} est en retard pour le traitement {treatment_name}. Veuillez prendre rendez-vous dès que possible.'],
            ['key' => 'medication_sms_message_late', 'value' => "URGENT: Votre {species} {pet_name} n'a pas réalisé le traitement {treatment_name}. Veuillez contacter votre vétérinaire immédiatement."],
            // Configurable windows (days)
            ['key' => 'sms_window_normal_days', 'value' => '30'],
            ['key' => 'sms_window_overdue_days', 'value' => '15'],
            ['key' => 'medication_window_normal_days', 'value' => '30'],
            ['key' => 'medication_window_overdue_days', 'value' => '15'],
        ];

        foreach ($defaults as $param) {
            $exists = DB::table('parameters')->where('key', $param['key'])->exists();
            if (!$exists) {
                DB::table('parameters')->insert($param);
            }
        }
    }

    public function down(): void
    {
        $keys = [
            'sms_message_normal',
            'sms_message_overdue',
            'sms_message_late',
            'medication_sms_message_normal',
            'medication_sms_message_overdue',
            'medication_sms_message_late',
            'sms_window_normal_days',
            'sms_window_overdue_days',
            'medication_window_normal_days',
            'medication_window_overdue_days',
        ];

        DB::table('parameters')->whereIn('key', $keys)->delete();
    }
};
