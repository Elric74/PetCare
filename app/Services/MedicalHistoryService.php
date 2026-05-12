<?php

namespace App\Services;

use App\Models\MedicalHistory;

class MedicalHistoryService
{
	public function storeHistory($petId, $histories): array
	{
		$savedHistories = [];
		$petId = (int) $petId;

		foreach ($histories as $history) {
			$id = $history['id'] ?? null;

			if ($id !== null && $id !== '') {
				$model = MedicalHistory::find((int) $id);
				if ($model !== null && (int) $model->pet_id === $petId) {
					$model->update($this->fillablePayload($history, $petId));
				}
			} else {
				$savedHistories[] = MedicalHistory::create($this->fillablePayload($history, $petId));
			}
		}

		return $savedHistories;
	}

	private function fillablePayload(array $history, int $petId): array
	{
		$structured = $history['structured_notes'] ?? null;
		if (is_array($structured) && $structured === []) {
			$structured = null;
		}

		return [
			'pet_id' => $petId,
			'condition' => $history['condition'] ?? null,
			'diagnosis_date' => $history['diagnosis_date'] ?? null,
			'treatment' => $history['treatment'] ?? null,
			'weight_g' => $history['weight_g'] ?? null,
			'notes' => $history['notes'] ?? null,
			'structured_notes' => $structured,
		];
	}

	public function fetchHistories($petId)
	{
		return MedicalHistory::where('pet_id', $petId)->get();
	}

	public function destroyHistory($petId, $historyId)
	{
		$history = MedicalHistory::findOrFail($historyId);

		if ((int) $history->pet_id !== (int) $petId) {
			throw new \Exception('The medical history does not belong to the specified pet');
		}

		$history->delete();

		return [
			'message' => 'Medical History successfully deleted!',
			'status' => 200
		];
	}
}
