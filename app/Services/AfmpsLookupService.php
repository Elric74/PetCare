<?php

namespace App\Services;

use App\Models\Medicament;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AfmpsLookupService
{
    /**
     * Lookup product info by key (CNK or GTIN) from local medicaments table,
     * with fallback to external AFMPS API if not found locally.
     * Returns [commercial_name => string|null, dosage => string|null, source => string]
     */
    public function lookupByKey(?string $value, string $field = 'gtin', bool $useExternalApi = true): array
    {
        if (!$value) {
            return ['commercial_name' => null, 'dosage' => null, 'source' => null];
        }

        // 1. Try local database first
        try {
            $med = Medicament::where($field, $value)->first();
            if ($med) {
                return [
                    'commercial_name' => $med->nom,
                    'dosage' => $this->extractDosage($med->nom),
                    'source' => 'local_db',
                ];
            }
        } catch (\Throwable $e) {
            Log::error('Medicament lookup error: ' . $e->getMessage(), [$field => $value]);
        }

        // 2. If not found locally and external API enabled, try AFMPS/Sam.be API
        if ($useExternalApi) {
            $externalResult = $this->lookupExternal($value, $field);
            if ($externalResult['commercial_name']) {
                return $externalResult;
            }
        }

        return ['commercial_name' => null, 'dosage' => null, 'source' => null];
    }

    /**
     * Lookup by GTIN (for backward compatibility).
     */
    public function lookupByGtin(?string $gtin): array
    {
        return $this->lookupByKey($gtin, 'gtin');
    }

    /**
     * Query external public medication database for medication info.
     * Tries Open Food Facts for pet food products with GTIN/EAN
     */
    private function lookupExternal(string $value, string $field): array
    {
        try {
            Log::info('External API lookup', ['value' => $value, 'field' => $field]);
            
            // Try Open Food Facts API (works for pet food with GTIN/EAN)
            if ($field === 'gtin' && strlen($value) >= 8) {
                $offResult = $this->queryOpenFoodFacts($value);
                if ($offResult['commercial_name']) {
                    return $offResult;
                }
            }
            
            Log::info('External API no results', ['value' => $value]);
            
        } catch (\Throwable $e) {
            Log::warning('External API error: ' . $e->getMessage(), ['value' => $value]);
        }
        
        return ['commercial_name' => null, 'dosage' => null, 'source' => null];
    }
    
    /**
     * Query Open Food Facts for product info (good for pet food, some medications)
     */
    private function queryOpenFoodFacts(string $gtin): array
    {
        try {
            $response = Http::timeout(5)
                ->withHeaders(['User-Agent' => 'PetCare-VetApp/1.0'])
                ->get("https://world.openfoodfacts.org/api/v2/product/{$gtin}.json");
            
            if ($response->successful()) {
                $data = $response->json();
                
                if (!empty($data['product']['product_name'])) {
                    $productName = $data['product']['product_name'];
                    
                    Log::info('OpenFoodFacts success', ['gtin' => $gtin, 'name' => $productName]);
                    
                    return [
                        'commercial_name' => $productName,
                        'dosage' => $this->extractDosage($productName),
                        'source' => 'openfoodfacts',
                        'external_data' => [
                            'brands' => $data['product']['brands'] ?? null,
                            'quantity' => $data['product']['quantity'] ?? null,
                        ],
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::debug('OpenFoodFacts query failed: ' . $e->getMessage());
        }
        
        return ['commercial_name' => null, 'dosage' => null, 'source' => null];
    }

    /**
     * Extract dosage from medication name (e.g., "250 µg/ml" or "100 mg/ml").
     */
    private function extractDosage(?string $name): ?string
    {
        if (!$name) return null;
        // Match patterns like "250 µg/ml", "100 mg/ml", "2kg", etc.
        if (preg_match('/(\d+[\.,]?\d*)\s*(µg|mg|kg|g|ml|%|UI|IU)\/?(?:ml|kg|g)?/i', $name, $m)) {
            return $m[0];
        }
        return null;
    }
}
