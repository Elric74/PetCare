<?php

namespace App\Http\Controllers;

use App\Models\Pet;
use App\Models\SmsLog;
use App\Models\Vaccination;
use App\Models\Medication;
use App\Models\LabReport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index() {
        $today = Carbon::now()->startOfDay();
        $normalWindowDays = (int) (\App\Models\Parameter::get('sms_window_normal_days', 30));
        $overdueWindowDays = (int) (\App\Models\Parameter::get('sms_window_overdue_days', 30));
        $normalWindowEnd = $today->copy()->addDays($normalWindowDays);
        $overdueWindowStart = $today->copy()->subDays($overdueWindowDays);

        // Build vaccination ID lists for each category but exclude any vaccination
        // that has a same-type future reminder (> 30 days) for the same pet.

        // Normal (reminder in [today, +30d]) — exclude vaccinations for deceased pets
        $normalVaccinationIds = Vaccination::whereHas('pet', function($q) {
                $q->where('decedee', false);
            })
          ->where('is_active', true)
          ->whereNotNull('reminder_date')
              ->where('reminder_date', '>=', $today)
              ->where('reminder_date', '<=', $normalWindowEnd)
              ->whereNotExists(function ($query) use ($normalWindowEnd) {
            $query->select(DB::raw(1))
                ->from('vaccinations as v2')
                ->whereColumn('v2.pet_id', 'vaccinations.pet_id')
                ->whereColumn('v2.vaccine_name', 'vaccinations.vaccine_name')
                ->where('v2.reminder_date', '>', $normalWindowEnd);
          })->pluck('id')->unique()->toArray();

        // Overdue < 1 month (reminder_date < today && > today - 30) — exclude deceased pets
        $overdueVaccinationIds = Vaccination::whereHas('pet', function($q) {
                $q->where('decedee', false);
            })
          ->where('is_active', true)
          ->whereNotNull('reminder_date')
              ->where('reminder_date', '<', $today)
              ->where('reminder_date', '>', $overdueWindowStart)
              ->whereNotExists(function ($query) use ($normalWindowEnd) {
            $query->select(DB::raw(1))
                ->from('vaccinations as v2')
                ->whereColumn('v2.pet_id', 'vaccinations.pet_id')
                ->whereColumn('v2.vaccine_name', 'vaccinations.vaccine_name')
                ->where('v2.reminder_date', '>', $normalWindowEnd);
          })->pluck('id')->unique()->toArray();

        // Overdue >= 1 month — exclude deceased pets
        $lateVaccinationIds = Vaccination::whereHas('pet', function($q) {
                $q->where('decedee', false);
            })
          ->where('is_active', true)
          ->whereNotNull('reminder_date')
              ->where('reminder_date', '<=', $overdueWindowStart)
              ->whereNotExists(function ($query) use ($normalWindowEnd) {
            $query->select(DB::raw(1))
                ->from('vaccinations as v2')
                ->whereColumn('v2.pet_id', 'vaccinations.pet_id')
                ->whereColumn('v2.vaccine_name', 'vaccinations.vaccine_name')
                ->where('v2.reminder_date', '>', $normalWindowEnd);
          })->pluck('id')->unique()->toArray();

        // Helper to fetch pets and include only the relevant vaccinations
        $fetchPetsByVaccinationIds = function (array $vaccIds) {
          if (empty($vaccIds)) return collect();

          $petIds = Vaccination::whereIn('id', $vaccIds)->pluck('pet_id')->unique()->toArray();

          return Pet::with(['client', 'vaccinations' => function ($q) use ($vaccIds) {
            $q->whereIn('id', $vaccIds)->orderBy('reminder_date', 'asc');
          }])->whereIn('id', $petIds)->where('decedee', false)->get();
        };

        $petsWithNormalReminders = $fetchPetsByVaccinationIds($normalVaccinationIds);
        $petsWithOverdueReminders = $fetchPetsByVaccinationIds($overdueVaccinationIds);
        $petsWithLateReminders = $fetchPetsByVaccinationIds($lateVaccinationIds);

        // Get sent SMS by category
        $sentNormal = SmsLog::where('sms_sent_as_normal', true)->pluck('vaccination_id')->toArray();
        $sentOverdue = SmsLog::where('sms_sent_as_overdue', true)->pluck('vaccination_id')->toArray();
        $sentLate = SmsLog::where('sms_sent_as_late', true)->pluck('vaccination_id')->toArray();

        // ========== MEDICATIONS REMINDERS ==========
        
        // Normal medication reminders (reminder in [today, +normalWindowDays])
        $medNormalWindowDays = (int) (\App\Models\Parameter::get('medication_window_normal_days', $normalWindowDays));
        $medOverdueWindowDays = (int) (\App\Models\Parameter::get('medication_window_overdue_days', $overdueWindowDays));
        $medNormalWindowEnd = $today->copy()->addDays($medNormalWindowDays);
        $medOverdueWindowStart = $today->copy()->subDays($medOverdueWindowDays);

        $normalMedicationIds = Medication::whereHas('pet', function($q) {
                $q->where('decedee', false);
            })
          ->where('is_active', true)
          ->whereNotNull('reminder_date')
          ->where('reminder_date', '>=', $today)
          ->where('reminder_date', '<=', $medNormalWindowEnd)
          ->whereNotExists(function ($query) use ($medNormalWindowEnd) {
            $query->select(DB::raw(1))
                ->from('medications as m2')
                ->whereColumn('m2.pet_id', 'medications.pet_id')
                ->whereColumn('m2.medication_name', 'medications.medication_name')
                ->where('m2.reminder_date', '>', $medNormalWindowEnd);
          })->pluck('id')->unique()->toArray();

        // Overdue < 1 month
        $overdueMedicationIds = Medication::whereHas('pet', function($q) {
                $q->where('decedee', false);
            })
          ->where('is_active', true)
          ->whereNotNull('reminder_date')
                  ->where('reminder_date', '<', $today)
                  ->where('reminder_date', '>', $medOverdueWindowStart)
                  ->whereNotExists(function ($query) use ($medNormalWindowEnd) {
            $query->select(DB::raw(1))
                ->from('medications as m2')
                ->whereColumn('m2.pet_id', 'medications.pet_id')
                ->whereColumn('m2.medication_name', 'medications.medication_name')
                    ->where('m2.reminder_date', '>', $medNormalWindowEnd);
          })->pluck('id')->unique()->toArray();

        // Overdue >= 1 month
        $lateMedicationIds = Medication::whereHas('pet', function($q) {
                $q->where('decedee', false);
            })
          ->where('is_active', true)
          ->whereNotNull('reminder_date')
                  ->where('reminder_date', '<=', $medOverdueWindowStart)
                  ->whereNotExists(function ($query) use ($medNormalWindowEnd) {
            $query->select(DB::raw(1))
                ->from('medications as m2')
                ->whereColumn('m2.pet_id', 'medications.pet_id')
                ->whereColumn('m2.medication_name', 'medications.medication_name')
                    ->where('m2.reminder_date', '>', $medNormalWindowEnd);
          })->pluck('id')->unique()->toArray();

        // Helper to fetch pets with medications
        $fetchPetsByMedicationIds = function (array $medIds) {
          if (empty($medIds)) return collect();

          $petIds = Medication::whereIn('id', $medIds)->pluck('pet_id')->unique()->toArray();

          return Pet::with(['client', 'medications' => function ($q) use ($medIds) {
            $q->whereIn('id', $medIds)->orderBy('reminder_date', 'asc');
          }])->whereIn('id', $petIds)->where('decedee', false)->get();
        };

        $petsWithNormalMedicationReminders = $fetchPetsByMedicationIds($normalMedicationIds);
        $petsWithOverdueMedicationReminders = $fetchPetsByMedicationIds($overdueMedicationIds);
        $petsWithLateMedicationReminders = $fetchPetsByMedicationIds($lateMedicationIds);

        // Get sent medication SMS by category
        $sentMedicationNormal = SmsLog::where('medication_sms_sent_as_normal', true)->pluck('medication_id')->toArray();
        $sentMedicationOverdue = SmsLog::where('medication_sms_sent_as_overdue', true)->pluck('medication_id')->toArray();
        $sentMedicationLate = SmsLog::where('medication_sms_sent_as_late', true)->pluck('medication_id')->toArray();

        $latestLabReports = LabReport::query()
            ->whereNotNull('pdf_path')
            ->orderByDesc('updated_date')
            ->orderByDesc('id')
            ->limit(3)
            ->get([
                'id',
                'pet_name',
                'owner_first_name',
                'owner_last_name',
                'reception_date',
                'pdf_path',
            ])
            ->map(function (LabReport $report): array {
                $ownerFullName = trim(($report->owner_first_name ?? '') . ' ' . ($report->owner_last_name ?? ''));

                return [
                    'id' => $report->id,
                    'pet_name' => $report->pet_name ?: 'Animal inconnu',
                    'owner_name' => $ownerFullName !== '' ? $ownerFullName : 'Propriétaire inconnu',
                    'reception_date' => optional($report->reception_date)->toDateString(),
                    'pdf_url' => route('lab-reports.pdf', ['labReport' => $report->id]),
                ];
            })
            ->values();

        $dashboardChannelsEnabled = [
            'vaccination' => [
                'normal' => [
                    'sms' => \App\Models\Parameter::isEnabled('sms_dashboard_normal_enabled', true),
                    'whatsapp' => \App\Models\Parameter::isEnabled('whatsapp_dashboard_normal_enabled', true),
                    'messenger' => \App\Models\Parameter::isEnabled('messenger_dashboard_normal_enabled', true),
                ],
                'overdue' => [
                    'sms' => \App\Models\Parameter::isEnabled('sms_dashboard_overdue_enabled', true),
                    'whatsapp' => \App\Models\Parameter::isEnabled('whatsapp_dashboard_overdue_enabled', true),
                    'messenger' => \App\Models\Parameter::isEnabled('messenger_dashboard_overdue_enabled', true),
                ],
                'late' => [
                    'sms' => \App\Models\Parameter::isEnabled('sms_dashboard_late_enabled', true),
                    'whatsapp' => \App\Models\Parameter::isEnabled('whatsapp_dashboard_late_enabled', true),
                    'messenger' => \App\Models\Parameter::isEnabled('messenger_dashboard_late_enabled', true),
                ],
            ],
            'medication' => [
                'normal' => [
                    'sms' => \App\Models\Parameter::isEnabled('medication_sms_dashboard_normal_enabled', true),
                    'whatsapp' => \App\Models\Parameter::isEnabled('medication_whatsapp_dashboard_normal_enabled', true),
                    'messenger' => \App\Models\Parameter::isEnabled('medication_messenger_dashboard_normal_enabled', true),
                ],
                'overdue' => [
                    'sms' => \App\Models\Parameter::isEnabled('medication_sms_dashboard_overdue_enabled', true),
                    'whatsapp' => \App\Models\Parameter::isEnabled('medication_whatsapp_dashboard_overdue_enabled', true),
                    'messenger' => \App\Models\Parameter::isEnabled('medication_messenger_dashboard_overdue_enabled', true),
                ],
                'late' => [
                    'sms' => \App\Models\Parameter::isEnabled('medication_sms_dashboard_late_enabled', true),
                    'whatsapp' => \App\Models\Parameter::isEnabled('medication_whatsapp_dashboard_late_enabled', true),
                    'messenger' => \App\Models\Parameter::isEnabled('medication_messenger_dashboard_late_enabled', true),
                ],
            ],
        ];

        return Inertia::render('Dashboard', [
          'dashboardChannelsEnabled' => $dashboardChannelsEnabled,
          'petsWithNormalReminders' => $petsWithNormalReminders,
          'petsWithOverdueReminders' => $petsWithOverdueReminders,
          'petsWithLateReminders' => $petsWithLateReminders,
          'sentNormal' => $sentNormal,
          'sentOverdue' => $sentOverdue,
          'sentLate' => $sentLate,
                  'petsWithNormalMedicationReminders' => $petsWithNormalMedicationReminders,
                  'petsWithOverdueMedicationReminders' => $petsWithOverdueMedicationReminders,
                  'petsWithLateMedicationReminders' => $petsWithLateMedicationReminders,
                  'sentMedicationNormal' => $sentMedicationNormal,
                  'sentMedicationOverdue' => $sentMedicationOverdue,
                  'sentMedicationLate' => $sentMedicationLate,
                  'latestLabReports' => $latestLabReports,
        ]);
    }
}
