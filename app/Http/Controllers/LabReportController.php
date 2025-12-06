<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Pet;
use App\Models\Image;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LabReportController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'owner_first_name' => 'nullable|string|max:255',
            'owner_last_name' => 'nullable|string|max:255',
            'owner_name' => 'nullable|string|max:255',
            'pet_name' => 'nullable|string|max:255',
            'pet_gender' => 'nullable|string|max:20',
            'reception_date' => 'nullable|string|max:255',
            'updated_date' => 'nullable|string|max:255',
            'pdf' => 'nullable|file|mimes:pdf|max:20480',
            'source' => 'nullable|string|max:50'
        ]);

        // Upsert client (owner)
        // Consolidate owner name (system uses single 'name' field)
        $ownerName = $validated['owner_name']
            ?? trim(implode(' ', array_filter([
                $validated['owner_first_name'] ?? null,
                $validated['owner_last_name'] ?? null,
            ])));

        $client = null;
        if ($ownerName) {
            $client = Client::firstOrCreate([
                'name' => $ownerName,
                'email' => ''
            ], [
                'email' => '',
                'phone_number' => null,
                'address' => null,
                'notes' => null,
            ]);
        }

        // Upsert pet attached to owner
        $pet = null;
        if ($client && !empty($validated['pet_name'])) {
            // Map detected animal type to correct species_id and breed_id
            $detectedType = strtolower($validated['pet_gender'] ?? '');
            
            if ($detectedType === 'cat') {
                $speciesId = 1;
                $breedId = 1111; // Default cat breed
            } elseif ($detectedType === 'dog') {
                $speciesId = 2;
                $breedId = 1112; // Default dog breed
            } else {
                $speciesId = 7; // Other/unknown species
                $breedId = null;
            }

            $pet = Pet::firstOrCreate([
                'name' => $validated['pet_name'],
                'client_id' => $client->id,
            ], [
                'gender' => 'à déterminer',
                'species_id' => $speciesId,
                'breed_id' => $breedId,
            ]);
        }

        // Store PDF if present
        $pdfPath = null;
        if ($request->hasFile('pdf')) {
            $filename = Str::slug(($validated['pet_name'] ?? 'report')) . '_' . time() . '.pdf';
            $pdfPath = $request->file('pdf')->storeAs('lab_reports', $filename, 'public');
            
            // Create image record if pet exists
            if ($pet && $pdfPath) {
                Image::create([
                    'pet_id' => $pet->id,
                    'path' => 'storage/' . $pdfPath,
                ]);
            }
        }

        return response()->json([
            'message' => 'Lab report ingested',
            'client' => $client,
            'pet' => $pet,
            'pdf_path' => $pdfPath,
            'reception_date' => $validated['reception_date'] ?? null,
            'updated_date' => $validated['updated_date'] ?? null,
            'source' => $validated['source'] ?? null,
        ], 201);
    }
}
