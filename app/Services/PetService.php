<?php

namespace App\Services;

use App\Models\Breed;
use App\Models\Pet;
use App\Models\Species;
use App\Models\Client;
use Illuminate\Support\Facades\Storage;

class PetService
{
    private function isTruthy($value): bool
    {
        // Accept common truthy forms from form submissions
        return in_array($value, [1, '1', true, 'true', 'on'], true);
    }

    public function createPet(array $data): void
    {
        $pet = Pet::create($data);

        if (array_key_exists('photo', $data) && $data['photo']->isValid()) {
            $this->handlePhotoUpload($pet, $data['photo']);
        }
    }

    public function updatePet($id, array $data): void
    {
        $pet = Pet::findOrFail($id);

        // Normalize dates when flags are unchecked
        if (array_key_exists('decedee', $data)) {
            if (!$this->isTruthy($data['decedee'])) {
                $data['date_deces'] = null;
            }
        }
        if (array_key_exists('is_sterilized', $data)) {
            if (!$this->isTruthy($data['is_sterilized'])) {
                $data['sterilized_at'] = null;
            }
        }

        $pet->update($data);

        if (array_key_exists('photo', $data) && $data['photo']->isValid()) {
            $this->handlePhotoUpload($pet, $data['photo']);
        }
    }

    public function deletePet($id): void
    {
        $pet = Pet::findOrFail($id);
        $pet->delete();
    }

    public function bulkDeletePets($ids)
    {
        Pet::destroy($ids);
    }

    protected function handlePhotoUpload(Pet $pet, $photo): void
    {
        $petId = $pet->id;
        $originalExt = strtolower($photo->getClientOriginalExtension());
        
        // Use Laravel Storage (storage/app/public) which has proper permissions
        $storagePath = 'images/pets/' . $petId;
        
        $filename = null;

        // HEIC files: save as-is (conversion must be done client-side)
        // Server conversion not possible: ImageMagick lacks libheif, FFmpeg lacks HEVC decoder
        if (in_array($originalExt, ['heic', 'heif'])) {
            $filename = uniqid() . '.' . $originalExt;
            Storage::disk('public')->putFileAs($storagePath, $photo, $filename);
        } else {
            // Non-HEIC files: use Laravel Storage directly
            $filename = uniqid() . '.' . $originalExt;
            Storage::disk('public')->putFileAs($storagePath, $photo, $filename);
        }

        // Path for database (relative to public/storage via symlink)
        $photoPath = 'storage/images/pets/' . $petId . '/' . $filename;

        $pet->photo = $photoPath;
        $pet->save();
    }

    public function fetchAllPets($page)
    {
        $perPage = 10;

        // Order living pets first (decedee = false / 0), then deceased (decedee = true / 1).
        // Within each group, sort by name alphabetically (A → Z).
        $pets = Pet::with('species', 'breed')
            ->orderByRaw('COALESCE(decedee, 0) ASC')
            ->orderBy('name', 'ASC')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'pets' => $pets,
            'links' => $pets->links(),
            'count' => Pet::count(),
            'meta' => [
                'currentPage' => $pets->currentPage(),
                'lastPage' => $pets->lastPage(),
                'totalItems' => $pets->total(),
                'perPage' => $pets->perPage(),
            ],
        ];
    }

    public function fetchAllSpecies()
    {
        return Species::query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function fetchAllBreeds($speciesId)
    {
        return Breed::where('species_id', $speciesId)->get();
    }

    public function search($keywords)
    {
        return Pet::where('name', 'like', '%' . $keywords . '%')
            ->with('client', 'species', 'breed')
            ->orderByRaw('COALESCE(decedee, 0) ASC')
            ->orderBy('name', 'ASC')
            ->get();
    }

    public function searchSpecies($name)
    {
        return Species::where('name', 'like', '%' . $name . '%')->get(['id', 'name']);
    }

    public function searchBreeds($speciesId)
    {
        return Breed::where('species_id', $speciesId)->get();
    }

    public function fetchAllClients()
    {
        return Client::take(10)->get(['id', 'name']);
    }

    public function searchClients($name)
    {
        return Client::where('name', 'like', "%$name%")->get(['id', 'name']);
    }
}
