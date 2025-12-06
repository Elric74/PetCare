<?php

namespace App\Http\Controllers;

use App\Http\Requests\InventoryMedocStoreRequest;
use App\Models\InventoryMedoc;
use App\Models\UnknownBarcode;
use App\Models\Medicament;
use App\Services\Gs1DatamatrixParser;
use App\Services\BarcodeMatchService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class InventoryMedocController extends Controller
{
    protected Gs1DatamatrixParser $parser;
    protected BarcodeMatchService $barcodeMatcher;

    public function __construct(Gs1DatamatrixParser $parser, BarcodeMatchService $barcodeMatcher)
    {
        $this->parser = $parser;
        $this->barcodeMatcher = $barcodeMatcher;
    }

    public function create(): Response
    {
        return Inertia::render('Inventory/Scan');
    }

    /**
     * Étape 1 : Scan initial - retourne le GTIN extrait et détermine si trouvé ou pas
     */
    public function scan(InventoryMedocStoreRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            
            // Clean control characters from input
            foreach (['raw', 'barcode', 'serial_number', 'lot_number'] as $field) {
                if (!empty($data[$field])) {
                    $data[$field] = preg_replace('/[\x00-\x1F\x7F]/', '', $data[$field]);
                }
            }
            
            // Parse GS1 DataMatrix if raw is present
            $extractedBarcode = null;
            if (!empty($data['raw'])) {
                $parsed = $this->parser->parse($data['raw']);
                \Log::info('Parsed GS1 code', ['raw' => $data['raw'], 'parsed' => $parsed]);
                $extractedBarcode = $parsed['gtin'] ?? null;
            }
            
            // If a direct barcode was submitted (linear scan), use it
            if (!empty($data['barcode'])) {
                $extractedBarcode = $data['barcode'];
            }
            
            if (!$extractedBarcode) {
                return response()->json(['message' => 'Aucun code-barres détecté'], 400);
            }
            
            // Try to find medicament by extracted barcode
            [$medicament, $barcode_type] = $this->barcodeMatcher->findByBarcode($extractedBarcode);
            
            if ($medicament) {
                // Cas 1 : Trouvé ! On peut créer directement l'InventoryMedoc
                return response()->json([
                    'status' => 'found',
                    'message' => 'Médicament trouvé',
                    'gtin' => $extractedBarcode,
                    'barcode_type' => $barcode_type,
                    'medicament' => [
                        'id' => $medicament->id,
                        'nom' => $medicament->nom,
                        'dosage' => $this->barcodeMatcher->extractDosage($medicament->nom),
                    ],
                ], 200);
            }
            
            // Cas 2 & 3 : Pas trouvé - demander CNK ou autre code
            return response()->json([
                'status' => 'not_found',
                'message' => 'Médicament non trouvé',
                'gtin' => $extractedBarcode,
                'raw' => $data['raw'] ?? null,
            ], 200);
            
        } catch (\Exception $e) {
            \Log::error('Scan error: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Étape 2 : Créer l'InventoryMedoc après confirmation (medicament trouvé)
     */
    public function confirmFound(InventoryMedocStoreRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            foreach (['raw', 'barcode', 'serial_number', 'lot_number'] as $field) {
                if (!empty($data[$field])) {
                    $data[$field] = preg_replace('/[\x00-\x1F\x7F]/', '', $data[$field]);
                }
            }
            
            $extractedBarcode = $data['barcode'] ?? null;
            if (!$extractedBarcode) {
                return response()->json(['message' => 'Code-barres manquant'], 400);
            }
            
            [$medicament, $barcode_type] = $this->barcodeMatcher->findByBarcode($extractedBarcode);
            if (!$medicament) {
                return response()->json(['message' => 'Médicament introuvable'], 404);
            }
            
            $data['user_id'] = auth()->id();
            $data['scanned_at'] = now();
            
            $record = InventoryMedoc::create($data);
            $record->update([
                'barcode' => $extractedBarcode,
                'barcode_type' => $barcode_type,
                'commercial_name' => $medicament->nom,
                'dosage' => $this->barcodeMatcher->extractDosage($medicament->nom),
            ]);
            
            return response()->json(['message' => 'Inventaire enregistré', 'id' => $record->id], 201);
        } catch (\Exception $e) {
            \Log::error('Confirm found error: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Étape 3a : Scan du CNK - chercher le medicament et mettre à jour barcode1
     */
    public function scanCnk(InventoryMedocStoreRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            foreach (['raw', 'barcode', 'serial_number', 'lot_number'] as $field) {
                if (!empty($data[$field])) {
                    $data[$field] = preg_replace('/[\x00-\x1F\x7F]/', '', $data[$field]);
                }
            }
            
            $scannedCode = $data['barcode'] ?? ($data['raw'] ?? null);
            $gtin = $request->input('gtin'); // le GTIN du premier scan
            
            if (!$scannedCode || !$gtin) {
                return response()->json(['message' => 'Code-barres ou GTIN manquant'], 400);
            }
            
            // Chercher le médicament par CNK (barcode2, barcode3, barcode4, barcode5, barcode6)
            $medicament = Medicament::where('barcode2', $scannedCode)
                ->orWhere('barcode3', $scannedCode)
                ->orWhere('barcode4', $scannedCode)
                ->orWhere('barcode5', $scannedCode)
                ->orWhere('barcode6', $scannedCode)
                ->first();
            
            if (!$medicament) {
                return response()->json(['message' => 'Aucun médicament trouvé avec ce code'], 404);
            }
            
            // Mettre à jour barcode1 si vide, sinon ajouter aux colonnes barcode3-6
            if (empty($medicament->barcode1)) {
                $medicament->update(['barcode1' => $gtin]);
            } elseif (empty($medicament->barcode3)) {
                $medicament->update(['barcode3' => $gtin]);
            } elseif (empty($medicament->barcode4)) {
                $medicament->update(['barcode4' => $gtin]);
            } elseif (empty($medicament->barcode5)) {
                $medicament->update(['barcode5' => $gtin]);
            } elseif (empty($medicament->barcode6)) {
                $medicament->update(['barcode6' => $gtin]);
            }
            
            // Créer l'InventoryMedoc
            $data['user_id'] = auth()->id();
            $data['scanned_at'] = now();
            $record = InventoryMedoc::create($data);
            $record->update([
                'barcode' => $gtin,
                'barcode_type' => 'barcode1',
                'commercial_name' => $medicament->nom,
                'dosage' => $this->barcodeMatcher->extractDosage($medicament->nom),
            ]);
            
            return response()->json([
                'message' => 'Médicament trouvé via CNK et GTIN lié',
                'medicament' => $medicament->only(['id', 'nom', 'barcode1', 'barcode2']),
                'inventory_id' => $record->id
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Scan CNK error: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Étape 3b : Non-CNK - créer UnknownBarcode et demander données manuelles
     */
    public function saveUnknown(InventoryMedocStoreRequest $request): JsonResponse
    {
        try {
            $gtin = $request->input('gtin');
            $raw = $request->input('raw');
            
            if (!$gtin) {
                return response()->json(['message' => 'GTIN manquant'], 400);
            }
            
            // Créer dans unknown_barcodes
            $unknownRecord = UnknownBarcode::create([
                'gtin' => $gtin,
                'barcode_scanned' => $raw,
                'user_id' => auth()->id(),
            ]);
            
            return response()->json([
                'status' => 'unknown_created',
                'message' => 'Code-barres enregistré comme inconnu. Veuillez saisir la dénomination commerciale.',
                'unknown_id' => $unknownRecord->id,
                'gtin' => $gtin,
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Save unknown error: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Étape 4 : Finaliser l'enregistrement inconnu avec les données manuelles
     */
    public function finalizeUnknown(InventoryMedocStoreRequest $request): JsonResponse
    {
        try {
            $unknownId = $request->input('unknown_id');
            $commercialName = $request->input('commercial_name');
            $dosage = $request->input('dosage');
            $gtin = $request->input('gtin');
            
            $unknownRecord = UnknownBarcode::find($unknownId);
            if (!$unknownRecord) {
                return response()->json(['message' => 'Enregistrement inconnu introuvable'], 404);
            }
            
            // Mettre à jour avec données manuelles
            $unknownRecord->update([
                'commercial_name' => $commercialName,
                'dosage' => $dosage,
            ]);
            
            // Créer l'InventoryMedoc correspondant
            $data = [
                'raw' => $unknownRecord->barcode_scanned,
                'barcode' => $gtin,
                'commercial_name' => $commercialName,
                'dosage' => $dosage,
                'user_id' => auth()->id(),
                'scanned_at' => now(),
            ];
            
            $record = InventoryMedoc::create($data);
            $record->update([
                'barcode' => $gtin,
                'barcode_type' => null, // Pas trouvé dans medicaments
                'commercial_name' => $commercialName,
                'dosage' => $dosage,
            ]);
            
            return response()->json([
                'message' => 'Inventaire enregistré',
                'id' => $record->id,
                'unknown_id' => $unknownRecord->id,
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Finalize unknown error: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }

    public function store(InventoryMedocStoreRequest $request): JsonResponse
    {
        // Legacy endpoint - use scan() instead
        return $this->scan($request);
    }
}
