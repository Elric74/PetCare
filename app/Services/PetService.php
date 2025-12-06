<?php

namespace App\Services;

use App\Models\Breed;
use App\Models\Pet;
use App\Models\Species;
use App\Models\Client;

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
        $directory = public_path('storage/images/pets/' . $petId);

        if (!file_exists($directory)) {
            mkdir($directory, 0777, true);
        }

        $filename = null;

        // If HEIC/HEIF, try to convert to JPEG for browser compatibility using ImageMagick CLI
        if (in_array($originalExt, ['heic', 'heif'])) {
            $filename = uniqid() . '.jpg';
            $outputPath = $directory . DIRECTORY_SEPARATOR . $filename;
            $inputPath = $photo->getPathname();

            // Try to convert using 'magick' command (ImageMagick CLI)
            $command = "magick \"{$inputPath}\" -quality 90 \"{$outputPath}\"";
            $output = [];
            $return = 0;

            exec($command, $output, $return);

            if ($return !== 0) {
                // Conversion failed: fall back to saving original file
                $filename = uniqid() . '.' . $originalExt;
                $photo->move($directory, $filename);
            }
        } else {
            // Non-HEIC files: save directly
            $filename = uniqid() . '.' . $originalExt;
            $photo->move($directory, $filename);
        }

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
        return Species::paginate(10)->all();
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
