<?php

namespace App\Services;

use App\Models\Medicament;

class BarcodeMatchService
{
    /**
     * Find medicament by any of the 6 barcodes.
     * Returns [medicament, barcode_type] on match, else [null, null].
     */
    public function findByBarcode(?string $code): array
    {
        if (!$code) {
            return [null, null];
        }

        // Try each barcode column in order (barcode1=GTIN, barcode2=CNK, barcode3-6=alternatives)
        foreach (['barcode1', 'barcode2', 'barcode3', 'barcode4', 'barcode5', 'barcode6'] as $field) {
            $med = Medicament::where($field, $code)->first();
            if ($med) {
                return [$med, $field];
            }
        }

        return [null, null];
    }

    /**
     * Extract dosage from medication name (e.g., "250 µg/ml" or "100 mg/ml").
     */
    public function extractDosage(?string $name): ?string
    {
        if (!$name) return null;
        if (preg_match('/(\d+[\.,]?\d*)\s*(µg|mg|kg|g|ml|%|UI|IU)\/?(?:ml|kg|g)?/i', $name, $m)) {
            return $m[0];
        }
        return null;
    }
}
