<?php
namespace App\Services;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\SmsLog;

class SmsService
{
    private function sendViaTwilio(string $to, string $from, string $message): array
    {
        $sid = env('TWILIO_ACCOUNT_SID');
        $token = env('TWILIO_AUTH_TOKEN');

        if (! $sid || ! $token || ! $from) {
            return ['success' => false, 'response' => 'Twilio credentials missing'];
        }

        try {
            $url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";
            $payload = [
                'To' => $to,
                'From' => $from,
                'Body' => $message,
            ];

            $response = Http::withBasicAuth($sid, $token)->asForm()->post($url, $payload);
            if ($response->successful()) {
                return ['success' => true, 'response' => $response->json()];
            }

            Log::error('SmsService: Twilio error', ['status' => $response->status(), 'body' => $response->body()]);
            return ['success' => false, 'response' => $response->body()];
        } catch (\Throwable $e) {
            Log::error('SmsService exception: ' . $e->getMessage());
            return ['success' => false, 'response' => $e->getMessage()];
        }
    }

    /**
     * Send an SMS via configured provider (Twilio) or simulate if not configured.
     *
     * @param string $to E.164 phone number
     * @param string $message
     * @return array ['success' => bool, 'response' => mixed]
     */
    /**
     * @param int|null $petId
     * @param string|null $category 'normal', 'overdue', or 'late'
     */
    public function send(string $to, string $message, $petId = null, $vaccinationId = null, $category = null): array
    {
        $sid = env('TWILIO_ACCOUNT_SID');
        $token = env('TWILIO_AUTH_TOKEN');
        $from = env('TWILIO_FROM');

        if ($sid && $token && $from) {
            try {
                $url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";
                $payload = [
                    'To' => $to,
                    'From' => $from,
                    'Body' => $message,
                ];

                $response = Http::withBasicAuth($sid, $token)->asForm()->post($url, $payload);

                if ($response->successful()) {
                    if ($petId) {
                        $logData = [
                            'pet_id' => $petId,
                            'vaccination_id' => $vaccinationId,
                            'message' => $message,
                            'sent_at' => now(),
                        ];
                        if ($category === 'normal') {
                            $logData['sms_sent_as_normal'] = true;
                        } elseif ($category === 'overdue') {
                            $logData['sms_sent_as_overdue'] = true;
                        } elseif ($category === 'late') {
                            $logData['sms_sent_as_late'] = true;
                        }
                        SmsLog::create($logData);
                    }
                    return ['success' => true, 'response' => $response->json()];
                }

                Log::error('SmsService: Twilio error', ['status' => $response->status(), 'body' => $response->body()]);
                return ['success' => false, 'response' => $response->body()];
            } catch (\Throwable $e) {
                Log::error('SmsService exception: ' . $e->getMessage());
                return ['success' => false, 'response' => $e->getMessage()];
            }
        }

        // No provider configured — simulate and log
        Log::info('SmsService (simulated) sending SMS', ['to' => $to, 'message' => $message]);
        if ($petId) {
            $logData = [
                'pet_id' => $petId,
                'vaccination_id' => $vaccinationId,
                'message' => $message,
                'sent_at' => now(),
            ];
            if ($category === 'normal') {
                $logData['sms_sent_as_normal'] = true;
            } elseif ($category === 'overdue') {
                $logData['sms_sent_as_overdue'] = true;
            } elseif ($category === 'late') {
                $logData['sms_sent_as_late'] = true;
            }
            SmsLog::create($logData);
        }
        return ['success' => true, 'response' => 'simulated'];
    }

    public function sendMedication(string $to, string $message, $petId = null, $medicationId = null, $category = null): array
    {
        $sid = env('TWILIO_ACCOUNT_SID');
        $token = env('TWILIO_AUTH_TOKEN');
        $from = env('TWILIO_FROM');

        if ($sid && $token && $from) {
            try {
                $url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";
                $payload = [
                    'To' => $to,
                    'From' => $from,
                    'Body' => $message,
                ];

                $response = Http::withBasicAuth($sid, $token)->asForm()->post($url, $payload);

                if ($response->successful()) {
                    if ($petId) {
                        $logData = [
                            'pet_id' => $petId,
                            'medication_id' => $medicationId,
                            'message' => $message,
                            'sent_at' => now(),
                        ];
                        if ($category === 'normal') {
                            $logData['medication_sms_sent_as_normal'] = true;
                        } elseif ($category === 'overdue') {
                            $logData['medication_sms_sent_as_overdue'] = true;
                        } elseif ($category === 'late') {
                            $logData['medication_sms_sent_as_late'] = true;
                        }
                        SmsLog::create($logData);
                    }
                    return ['success' => true, 'response' => $response->json()];
                }

                Log::error('SmsService: Twilio error', ['status' => $response->status(), 'body' => $response->body()]);
                return ['success' => false, 'response' => $response->body()];
            } catch (\Throwable $e) {
                Log::error('SmsService exception: ' . $e->getMessage());
                return ['success' => false, 'response' => $e->getMessage()];
            }
        }

        // No provider configured — simulate and log
        Log::info('SmsService (simulated) sending Medication SMS', ['to' => $to, 'message' => $message]);
        if ($petId) {
            $logData = [
                'pet_id' => $petId,
                'medication_id' => $medicationId,
                'message' => $message,
                'sent_at' => now(),
            ];
            if ($category === 'normal') {
                $logData['medication_sms_sent_as_normal'] = true;
            } elseif ($category === 'overdue') {
                $logData['medication_sms_sent_as_overdue'] = true;
            } elseif ($category === 'late') {
                $logData['medication_sms_sent_as_late'] = true;
            }
            SmsLog::create($logData);
        }
        return ['success' => true, 'response' => 'simulated'];
    }

    public function sendWhatsapp(string $toE164, string $message): array
    {
        $whatsappFrom = env('TWILIO_WHATSAPP_FROM');
        if (! $whatsappFrom) {
            return ['success' => false, 'response' => 'TWILIO_WHATSAPP_FROM missing'];
        }

        $to = str_starts_with($toE164, 'whatsapp:') ? $toE164 : 'whatsapp:' . $toE164;
        $from = str_starts_with($whatsappFrom, 'whatsapp:') ? $whatsappFrom : 'whatsapp:' . $whatsappFrom;

        return $this->sendViaTwilio($to, $from, $message);
    }
}
