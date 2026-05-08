<?php

namespace App\Services;

use App\Models\Breed;
use App\Models\Pet;
use App\Models\Species;
use App\Models\Client;
use Illuminate\Support\Facades\Storage;

class PetService
{
    private function applySpeciesFilter($query, ?string $speciesFilter)
    {
        if (!$speciesFilter) {
            return $query;
        }

        if ($speciesFilter === 'cats') {
            return $query->whereHas('species', function ($speciesQuery) {
                $speciesQuery->whereRaw('LOWER(name) LIKE ?', ['%chat%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%cat%']);
            });
        }

        if ($speciesFilter === 'dogs') {
            return $query->whereHas('species', function ($speciesQuery) {
                $speciesQuery->whereRaw('LOWER(name) LIKE ?', ['%chien%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%dog%']);
            });
        }

        if ($speciesFilter === 'others') {
            return $query->whereHas('species', function ($speciesQuery) {
                $speciesQuery
                    ->whereRaw('LOWER(name) NOT LIKE ?', ['%chat%'])
                    ->whereRaw('LOWER(name) NOT LIKE ?', ['%cat%'])
                    ->whereRaw('LOWER(name) NOT LIKE ?', ['%chien%'])
                    ->whereRaw('LOWER(name) NOT LIKE ?', ['%dog%']);
            });
        }

        return $query;
    }

    private function getSpeciesCounts(): array
    {
        $cats = Pet::query()
            ->whereHas('species', function ($speciesQuery) {
                $speciesQuery->whereRaw('LOWER(name) LIKE ?', ['%chat%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%cat%']);
            })
            ->count();

        $dogs = Pet::query()
            ->whereHas('species', function ($speciesQuery) {
                $speciesQuery->whereRaw('LOWER(name) LIKE ?', ['%chien%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%dog%']);
            })
            ->count();

        $total = Pet::query()->count();
        $others = max(0, $total - $cats - $dogs);

        return [
            'cats' => $cats,
            'dogs' => $dogs,
            'others' => $others,
        ];
    }

    private function latestPetIds(int $limit = 5): array
    {
        return Pet::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function applyNewPetsOrdering($query, array $latestIds)
    {
        if (empty($latestIds)) {
            return $query
                ->select('pets.*')
                ->selectRaw('0 as is_new')
                ->orderBy('name', 'ASC');
        }

        $placeholders = implode(',', array_fill(0, count($latestIds), '?'));
        $isNewSql = "CASE WHEN pets.id IN ($placeholders) THEN 1 ELSE 0 END";

        return $query
            ->select('pets.*')
            ->selectRaw("$isNewSql as is_new", $latestIds)
            ->orderByRaw("$isNewSql DESC", $latestIds)
            ->orderBy('name', 'ASC');
    }

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

    public function fetchAllPets($page, ?string $speciesFilter = null)
    {
        $perPage = 10;
        $latestIds = $this->latestPetIds(5);

        $petsQuery = Pet::query()->with('species', 'breed');
        $petsQuery = $this->applySpeciesFilter($petsQuery, $speciesFilter);

        $pets = $this->applyNewPetsOrdering($petsQuery, $latestIds)
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'pets' => $pets,
            'links' => $pets->links(),
            'count' => Pet::count(),
            'species_counts' => $this->getSpeciesCounts(),
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
        $latestIds = $this->latestPetIds(5);

        $query = Pet::query()
            ->where('name', 'like', '%' . $keywords . '%')
            ->with('client', 'species', 'breed');

        return $this->applyNewPetsOrdering($query, $latestIds)->get();
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
