<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LabReport;
use App\Models\Pet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class LabReportController extends Controller
{
    /**
     * Return update dates for already imported Zoolyx report IDs.
     */
    public function syncStatus(Request $request)
    {
        $ids = $request->query('ids', []);

        if (!is_array($ids)) {
            $ids = array_filter(explode(',', (string) $ids));
        }

        $ids = array_values(array_filter(array_map('strval', $ids)));

        if (empty($ids)) {
            return response()->json(['data' => []]);
        }

        $reports = LabReport::query()
            ->whereIn('zoolyx_report_id', $ids)
            ->get(['zoolyx_report_id', 'updated_date']);

        $data = [];
        foreach ($reports as $report) {
            $data[$report->zoolyx_report_id] = [
                'updated_date' => optional($report->updated_date)->format('Y-m-d'),
            ];
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'zoolyx_report_id' => 'required|string',
            'pet_name' => 'required|string',
            'pet_species' => 'nullable|string',
            'owner_first_name' => 'nullable|string',
            'owner_last_name' => 'nullable|string',
            'reception_date' => 'required|date',
            'updated_date' => 'nullable|date',
            'pdf_file' => 'required|file|mimes:pdf|max:10240',
        ]);

        // Check if already imported
        $existing = LabReport::where('zoolyx_report_id', $validated['zoolyx_report_id'])->first();

        // Store PDF file (both create and update flows)
        $pdfFile = $request->file('pdf_file');
        $filename = date('Y-m-d') . '_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $validated['pet_name']) . '_' . $validated['zoolyx_report_id'] . '.pdf';
        $pdfPath = $pdfFile->storeAs('lab_reports', $filename, 'public');

        // Try to match pet by name and owner
        $petId = $this->findPetId($validated);

        if ($existing) {
            $incomingUpdatedDate = $validated['updated_date'] ?? null;
            $existingUpdatedDate = optional($existing->updated_date)->format('Y-m-d');

            // Keep old behaviour when nothing changed.
            if ($incomingUpdatedDate === $existingUpdatedDate) {
                Storage::disk('public')->delete($pdfPath);

                return response()->json([
                    'status' => 'duplicate',
                    'message' => 'Ce rapport a déjà été importé',
                    'report_id' => $existing->id,
                ], 200);
            }

            // Report changed: replace file + metadata.
            if (!empty($existing->pdf_path)) {
                Storage::disk('public')->delete($existing->pdf_path);
            }

            $existing->update([
                'pet_id' => $petId,
                'pet_name' => $validated['pet_name'],
                'pet_species' => $validated['pet_species'],
                'owner_first_name' => $validated['owner_first_name'],
                'owner_last_name' => $validated['owner_last_name'],
                'reception_date' => $validated['reception_date'],
                'updated_date' => $validated['updated_date'],
                'pdf_path' => $pdfPath,
            ]);

            Log::info('Lab report updated', [
                'report_id' => $existing->id,
                'zoolyx_id' => $validated['zoolyx_report_id'],
                'pet_name' => $validated['pet_name'],
            ]);

            return response()->json([
                'status' => 'updated',
                'message' => 'Rapport mis à jour avec succès',
                'report_id' => $existing->id,
                'pet_matched' => $petId !== null,
            ], 200);
        }

        // Create lab report
        $labReport = LabReport::create([
            'zoolyx_report_id' => $validated['zoolyx_report_id'],
            'pet_id' => $petId,
            'pet_name' => $validated['pet_name'],
            'pet_species' => $validated['pet_species'],
            'owner_first_name' => $validated['owner_first_name'],
            'owner_last_name' => $validated['owner_last_name'],
            'reception_date' => $validated['reception_date'],
            'updated_date' => $validated['updated_date'],
            'pdf_path' => $pdfPath,
        ]);

        Log::info('Lab report imported', [
            'report_id' => $labReport->id,
            'zoolyx_id' => $validated['zoolyx_report_id'],
            'pet_name' => $validated['pet_name'],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Rapport importé avec succès',
            'report_id' => $labReport->id,
            'pet_matched' => $petId !== null,
        ], 201);
    }

    /**
     * Try to find matching pet in database
     */
    private function findPetId(array $data): ?int
    {
        if (empty($data['pet_name'])) {
            return null;
        }

        // Try exact match first
        $query = Pet::where('name', 'LIKE', '%' . $data['pet_name'] . '%');

        // Add owner filter if available
        if (!empty($data['owner_last_name'])) {
            $query->whereHas('client', function ($q) use ($data) {
                $q->where('name', 'LIKE', '%' . $data['owner_last_name'] . '%');
            });
        }

        $pet = $query->first();
        return $pet?->id;
    }
}
