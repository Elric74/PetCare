<?php

namespace App\Services;

use App\Models\Medication;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MedicationService
{
	public function storeMedication($petId, $medications)
	{
		$savedMedications = [];

		foreach ($medications as $medication) {
			// Ensure pet_id is set
			$medication['pet_id'] = $petId;
			$medication['is_active'] = array_key_exists('is_active', $medication)
				? (bool) $medication['is_active']
				: true;

			// Compute reminder_date only for active reminders with periodicity.
			if (
				$medication['is_active'] === true
				&& !empty($medication['administered_at'])
				&& !empty($medication['frequency'])
			) {
				$medication['reminder_date'] = $this->computeReminderDate($medication['administered_at'], $medication['frequency']);
			} else {
				$medication['reminder_date'] = null;
			}

			if (isset($medication['id'])) {
				Medication::find($medication['id'])->update($medication);
			} else {
				$savedMedication = Medication::updateOrCreate(
					['id' => $medication['id']],
					$medication
				);

				$savedMedications[] = $savedMedication;
			}
		}

		return $savedMedications;
	}

	public function fetchMedications($petId)
	{
		$medications = Medication::where('pet_id', $petId)
			->orderBy('administered_at', 'desc')
			->orderBy('created_at', 'desc')
			->get();

		return $medications;

	}

	public function stopMedicationReminder($petId, $medicationId)
	{
		$medication = Medication::findOrFail($medicationId);

		if ((int) $medication->pet_id !== (int) $petId) {
			throw new \Exception('The medication does not belong to the specified pet');
		}

		$medication->update([
			'is_active' => false,
			'reminder_date' => null,
		]);

		return $medication->fresh();
	}

	public function destroyMedication($petId, $medicationId)
	{
		$medication = Medication::findOrFail($medicationId);

		if ((int) $medication->pet_id !== (int) $petId) {
			throw new \Exception('The medication does not belong to the specified pet');
		}

		$medication->delete();

		return [
			'message' => 'Medication successfully deleted!',
			'status' => 200
		];
	}

	private function computeReminderDate(string $administeredAt, string $frequency): string
	{
		// frequency codes like '1m', '3m', '6m', '1y', '2y'
		$date = new \DateTime($administeredAt);
		switch ($frequency) {
			case '1m':
				$date->modify('+1 month');
				break;
			case '3m':
				$date->modify('+3 months');
				break;
			case '6m':
				$date->modify('+6 months');
				break;
			case '1y':
				$date->modify('+1 year');
				break;
			case '2y':
				$date->modify('+2 years');
				break;
			default:
				// If an unknown code, do not change date (or set null)
				// Here we default to administered date without shift
				break;
		}
		return $date->format('Y-m-d');
	}
}