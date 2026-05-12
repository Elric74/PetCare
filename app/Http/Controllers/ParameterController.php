<?php

namespace App\Http\Controllers;

use App\Models\Parameter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ParameterController extends Controller
{
    public function index()
    {
        $parameters = Parameter::all();
        
        // Ensure default SMS message templates exist
        $defaults = [
            'sms_message_normal' => 'Ici votre vétérinaire, votre {species} {pet_name} a besoin d\'avoir son rappel de vaccin {vaccine_name} avant le {date}. Merci de prendre rdv au plus vite.',
            'sms_message_overdue' => 'Rappel: Votre {species} {pet_name} est en retard pour son vaccin {vaccine_name}. Veuillez prendre rendez-vous dès que possible.',
            'sms_message_late' => 'URGENT: Votre {species} {pet_name} n\'a pas eu son vaccin {vaccine_name} depuis très longtemps. Veuillez contacter votre vétérinaire immédiatement.',
            'whatsapp_message_normal' => 'Bonjour, votre {species} {pet_name} a besoin de son rappel de vaccin {vaccine_name} avant le {date}. Merci de prendre rendez-vous.',
            'whatsapp_message_overdue' => 'Rappel WhatsApp: votre {species} {pet_name} est en retard pour le vaccin {vaccine_name}. Merci de nous contacter.',
            'whatsapp_message_late' => 'Message urgent: votre {species} {pet_name} doit refaire le vaccin {vaccine_name}. Merci de prendre rendez-vous rapidement.',
            'messenger_message_normal' => 'Bonjour, votre {species} {pet_name} a besoin de son rappel de vaccin {vaccine_name} avant le {date}. Merci de prendre rendez-vous.',
            'messenger_message_overdue' => 'Rappel Messenger: votre {species} {pet_name} est en retard pour le vaccin {vaccine_name}. Merci de nous contacter.',
            'messenger_message_late' => 'Message urgent: votre {species} {pet_name} doit refaire le vaccin {vaccine_name}. Merci de prendre rendez-vous rapidement.',
            // Treatment SMS templates
            'medication_sms_message_normal' => 'Ici votre vétérinaire, votre {species} {pet_name} a un traitement {treatment_name} à réaliser avant le {date}. Merci de prendre rdv au plus vite.',
            'medication_sms_message_overdue' => 'Rappel: Votre {species} {pet_name} est en retard pour le traitement {treatment_name}. Veuillez prendre rendez-vous dès que possible.',
            'medication_sms_message_late' => 'URGENT: Votre {species} {pet_name} n\'a pas réalisé le traitement {treatment_name}. Veuillez contacter votre vétérinaire immédiatement.',
            'medication_whatsapp_message_normal' => 'Bonjour, votre {species} {pet_name} doit faire le traitement {medication_name} avant le {date}. Merci de prendre rendez-vous.',
            'medication_whatsapp_message_overdue' => 'Rappel WhatsApp: votre {species} {pet_name} est en retard pour le traitement {medication_name}. Merci de nous contacter.',
            'medication_whatsapp_message_late' => 'Message urgent: votre {species} {pet_name} doit refaire le traitement {medication_name}. Merci de prendre rendez-vous rapidement.',
            'medication_messenger_message_normal' => 'Bonjour, votre {species} {pet_name} doit faire le traitement {medication_name} avant le {date}. Merci de prendre rendez-vous.',
            'medication_messenger_message_overdue' => 'Rappel Messenger: votre {species} {pet_name} est en retard pour le traitement {medication_name}. Merci de nous contacter.',
            'medication_messenger_message_late' => 'Message urgent: votre {species} {pet_name} doit refaire le traitement {medication_name}. Merci de prendre rendez-vous rapidement.',
            // Configurable windows (days)
            'sms_window_normal_days' => '30',
            'sms_window_overdue_days' => '15',
            'medication_window_normal_days' => '30',
            'medication_window_overdue_days' => '15',
            // Dashboard: show / allow SMS send buttons per reminder type (1 = on)
            'sms_dashboard_normal_enabled' => '1',
            'sms_dashboard_overdue_enabled' => '1',
            'sms_dashboard_late_enabled' => '1',
            'medication_sms_dashboard_normal_enabled' => '1',
            'medication_sms_dashboard_overdue_enabled' => '1',
            'medication_sms_dashboard_late_enabled' => '1',
            'whatsapp_dashboard_normal_enabled' => '1',
            'whatsapp_dashboard_overdue_enabled' => '1',
            'whatsapp_dashboard_late_enabled' => '1',
            'messenger_dashboard_normal_enabled' => '1',
            'messenger_dashboard_overdue_enabled' => '1',
            'messenger_dashboard_late_enabled' => '1',
            'medication_whatsapp_dashboard_normal_enabled' => '1',
            'medication_whatsapp_dashboard_overdue_enabled' => '1',
            'medication_whatsapp_dashboard_late_enabled' => '1',
            'medication_messenger_dashboard_normal_enabled' => '1',
            'medication_messenger_dashboard_overdue_enabled' => '1',
            'medication_messenger_dashboard_late_enabled' => '1',
        ];

        foreach ($defaults as $key => $value) {
            if (!Parameter::where('key', $key)->exists()) {
                Parameter::create(['key' => $key, 'value' => $value]);
            }
        }

        $parameters = Parameter::all();

        return Inertia::render('Parameters/Index', [
            'parameters' => $parameters,
        ]);
    }

    public function update(Request $request, Parameter $parameter)
    {
        $validated = $request->validate([
            // Accept numeric or string; store as string
            'value' => 'required',
        ]);

        $parameter->update(['value' => (string) $validated['value']]);

        return response()->json(['success' => true, 'message' => 'Paramètre mis à jour avec succès']);
    }
}
