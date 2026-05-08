<?php

namespace App\Http\Controllers;

use App\Models\Image;
use App\Models\LabReport;
use App\Models\Pet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LabReportAssociationController extends Controller
{
    public function index(): Response
    {
        $labReports = LabReport::query()
            ->orderByDesc('updated_date')
            ->orderByDesc('id')
            ->get([
                'id',
                'zoolyx_report_id',
                'pet_id',
                'pet_name',
                'owner_first_name',
                'owner_last_name',
                'reception_date',
                'updated_date',
                'pdf_path',
            ]);

        $pets = Pet::query()
            ->with('client:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'client_id'])
            ->map(function (Pet $pet): array {
                $ownerName = $pet->client?->name;
                $label = $pet->name;
                if (!empty($ownerName)) {
                    $label .= ' (' . $ownerName . ')';
                }

                return [
                    'id' => $pet->id,
                    'name' => $pet->name,
                    'owner_name' => $ownerName,
                    'label' => $label,
                ];
            })
            ->values();

        return Inertia::render('LabReports/Associate', [
            'labReports' => $labReports,
            'pets' => $pets,
        ]);
    }

    public function associate(Request $request, LabReport $labReport): JsonResponse
    {
        $validated = $request->validate([
            'pet_id' => ['required', 'integer', 'exists:pets,id'],
            'match_state' => ['nullable', 'string', 'in:green,orange,neutral'],
        ]);

        $petId = (int) $validated['pet_id'];
        $labReport->pet_id = $petId;
        $labReport->save();

        $rawPath = ltrim((string) $labReport->pdf_path, '/');
        $galleryPath = str_starts_with($rawPath, 'storage/')
            ? $rawPath
            : 'storage/' . $rawPath;

        // Keep a single gallery entry for this PDF path.
        Image::query()->where('path', $galleryPath)->delete();
        Image::query()->firstOrCreate([
            'pet_id' => $petId,
            'path' => $galleryPath,
        ]);

        // If association is not considered fully reliable, enrich client name with Zoolyx owner info.
        $matchState = $validated['match_state'] ?? 'neutral';
        if (in_array($matchState, ['orange', 'neutral'], true)) {
            $pet = Pet::query()->with('client')->find($petId);
            $ownerFullName = trim(trim((string) $labReport->owner_first_name) . ' ' . trim((string) $labReport->owner_last_name));

            if ($pet?->client && $ownerFullName !== '') {
                $currentName = (string) $pet->client->name;
                if (!str_contains(mb_strtolower($currentName), mb_strtolower($ownerFullName))) {
                    $pet->client->name = trim($currentName . ' | ' . $ownerFullName);
                    $pet->client->save();
                }
            }
        }

        return response()->json([
            'message' => 'Prise de sang associée avec succès.',
            'lab_report_id' => $labReport->id,
            'pet_id' => $petId,
        ]);
    }
}
