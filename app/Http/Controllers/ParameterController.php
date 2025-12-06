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
            // Treatment SMS templates
            'medication_sms_message_normal' => 'Ici votre vétérinaire, votre {species} {pet_name} a un traitement {treatment_name} à réaliser avant le {date}. Merci de prendre rdv au plus vite.',
            'medication_sms_message_overdue' => 'Rappel: Votre {species} {pet_name} est en retard pour le traitement {treatment_name}. Veuillez prendre rendez-vous dès que possible.',
            'medication_sms_message_late' => 'URGENT: Votre {species} {pet_name} n\'a pas réalisé le traitement {treatment_name}. Veuillez contacter votre vétérinaire immédiatement.',
            // Configurable windows (days)
            'sms_window_normal_days' => '30',
            'sms_window_overdue_days' => '15',
            'medication_window_normal_days' => '30',
            'medication_window_overdue_days' => '15',
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
