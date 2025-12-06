<?php

namespace App\Services;

use App\Models\Medicament;

class AfmpsLookupService
{
    /**
     * Lookup product info by key (CNK or GTIN) from local medicaments table.
     * Returns [commercial_name => string|null, dosage => string|null]
     */
    public function lookupByKey(?string $value, string $field = 'gtin'): array
    {
        if (!$value) {
            return ['commercial_name' => null, 'dosage' => null];
        }

        try {
            $med = Medicament::where($field, $value)->first();
            if ($med) {
                return [
                    'commercial_name' => $med->nom,
                    'dosage' => $this->extractDosage($med->nom),
                ];
            }
        } catch (\Throwable $e) {
            \Log::error('Medicament lookup error: ' . $e->getMessage(), [$field => $value]);
        }

        return ['commercial_name' => null, 'dosage' => null];
    }

    /**
     * Lookup by GTIN (for backward compatibility).
     */
    public function lookupByGtin(?string $gtin): array
    {
        return $this->lookupByKey($gtin, 'gtin');
    }

    /**
     * Extract dosage from medication name (e.g., "250 µg/ml" or "100 mg/ml").
     */
    private function extractDosage(?string $name): ?string
    {
        if (!$name) return null;
        // Match patterns like "250 µg/ml", "100 mg/ml", etc.
        if (preg_match('/(\d+[\.,]?\d*)\s*(µg|mg|g|ml|%|UI|IU)\/?(ml|g)?/i', $name, $m)) {
            return $m[0];
        }
        return null;
    }
}
