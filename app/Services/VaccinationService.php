<?php

namespace App\Services;

use App\Models\Pet;
use App\Models\Vaccination;
use App\Models\Vaccine;

class VaccinationService
{
  public function storeVaccinations($petId, $vaccinations)
{
    $pet = Pet::findOrFail($petId);
    $savedVaccinations = [];

    foreach ($vaccinations as $vaccination) {
        $vaccination['is_active'] = array_key_exists('is_active', $vaccination)
            ? (bool) $vaccination['is_active']
            : true;

        if (isset($vaccination['id']) && $vaccination['id']) {
            $existingVaccination = Vaccination::find($vaccination['id']);
            if ($existingVaccination) {
                $existingVaccination->update($vaccination);
            }
        } else {
            // If the 'id' key is not set or is null, create a new Vaccination record
            $vaccination['pet_id'] = $petId; // Assuming 'pet_id' is a foreign key in the 'vaccinations' table
            $savedVaccination = Vaccination::create($vaccination);
            $savedVaccinations[] = $savedVaccination;
        }
    }
    return $savedVaccinations;
}

  public function fetchVaccinations($petId)
  {
      $vaccinations = Vaccination::where('pet_id', $petId)->get();

      return $vaccinations;
  }

  public function stopVaccinationReminder($petId, $vaccinationId)
  {
    $pet = Pet::findOrFail($petId);
    $vaccination = Vaccination::findOrFail($vaccinationId);

    if ((int) $vaccination->pet_id !== (int) $pet->id) {
      throw new \Exception('The vaccination does not belong to the specified pet');
    }

    $vaccination->update([
      'is_active' => false,
    ]);

    return $vaccination->fresh();
  }
  public function destroyVaccination($petId, $vaccinationId)
  {
    $pet = Pet::findOrFail($petId);
    $vaccination = Vaccination::findOrFail($vaccinationId);

    if ((int) $vaccination->pet_id !== (int) $pet->id) {
      throw new \Exception('The vaccination does not belong to the specified pet');
    }

    $vaccination->delete();

    return [
      'message' => 'Vaccination successfully deleted!',
      'status' => 200
    ];
  }

  public function fetchVaccinesBySpecies($speciesId)
  {
    $vaccines = Vaccine::where('species_id', $speciesId)->get(['id', 'name']);
    return $vaccines;
  }
}