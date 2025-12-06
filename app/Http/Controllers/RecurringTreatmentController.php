<?php

namespace App\Http\Controllers;

use App\Models\RecurringTreatment;
use App\Models\Species;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecurringTreatmentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('RecurringTreatments/Index');
    }

    public function fetchAll(Request $request): JsonResponse
    {
        $page = $request->query('page', 1);
        $perPage = 10;

        $treatments = RecurringTreatment::with('species')
            ->orderBy('updated_at', 'desc')
            ->orderBy('created_at', 'desc')
            ->orderBy('name')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json($treatments);
    }

    public function fetchBySpecies(?int $speciesId = null): JsonResponse
    {
        $query = RecurringTreatment::with('species')
            ->orderBy('updated_at', 'desc')
            ->orderBy('created_at', 'desc')
            ->orderBy('name');

        if ($speciesId) {
            $query->where(function ($q) use ($speciesId) {
                $q->whereNull('species_id')->orWhere('species_id', $speciesId);
            });
        }

        $treatments = $query->get();

        return response()->json($treatments);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'periodicity' => 'required|string',
            'species_id' => 'nullable|exists:species,id'
        ]);

        $treatment = RecurringTreatment::create($validated);

        return response()->json([
            'message' => 'Traitement récurrent ajouté avec succès!',
            'treatment' => $treatment->load('species')
        ], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'periodicity' => 'required|string',
            'species_id' => 'nullable|exists:species,id'
        ]);

        $treatment = RecurringTreatment::findOrFail($id);
        $treatment->update($validated);

        return response()->json([
            'message' => 'Traitement récurrent modifié avec succès!',
            'treatment' => $treatment->load('species')
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $treatment = RecurringTreatment::findOrFail($id);
        $treatment->delete();

        return response()->json([
            'message' => 'Traitement récurrent supprimé avec succès!'
        ]);
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'selectedIds' => 'required|array',
            'selectedIds.*' => 'exists:recurring_treatments,id'
        ]);

        RecurringTreatment::whereIn('id', $validated['selectedIds'])->delete();

        return response()->json([
            'message' => 'Traitements récurrents supprimés avec succès!'
        ]);
    }

    public function fetchSpecies(): JsonResponse
    {
        $species = Species::orderBy('name')->get();
        return response()->json($species);
    }
}
