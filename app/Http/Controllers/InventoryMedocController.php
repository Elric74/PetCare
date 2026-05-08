<?php

namespace App\Http\Controllers;

use App\Http\Requests\InventoryMedocStoreRequest;
use App\Models\InventoryMedoc;
use App\Models\UnknownBarcode;
use App\Models\Medicament;
use App\Services\Gs1DatamatrixParser;
use App\Services\BarcodeMatchService;
use App\Services\AfmpsLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InventoryMedocController extends Controller
{
    protected Gs1DatamatrixParser $parser;
    protected BarcodeMatchService $barcodeMatcher;
    protected AfmpsLookupService $afmpsLookup;

    public function __construct(
        Gs1DatamatrixParser $parser, 
        BarcodeMatchService $barcodeMatcher,
        AfmpsLookupService $afmpsLookup
    ) {
        $this->parser = $parser;
        $this->barcodeMatcher = $barcodeMatcher;
        $this->afmpsLookup = $afmpsLookup;
    }

    public function index(): Response
    {
        $annee = (int) request('annee', date('Y'));
        $priceField = "prix_$annee";
        
        \Log::info('Inventory Index - Filtering by year', ['annee' => $annee, 'type' => gettype($annee)]);
        
        // Récupérer tous les scans (sans groupement SQL)
        $allScans = InventoryMedoc::where('annee_inventaire', $annee)
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Get medicaments for ALL scans
        $barcodes = $allScans->pluck('barcode')->filter()->unique()->toArray();
        $names = $allScans->pluck('commercial_name')->filter()->unique()->toArray();
        
        $medicamentsByBarcode = \App\Models\Medicament::whereIn('barcode1', $barcodes)
            ->get()
            ->keyBy('barcode1');
            
        $medicamentsByName = \App\Models\Medicament::whereIn('nom', $names)
            ->get()
            ->keyBy('nom');
        
        // Grouper intelligemment: boîtes complètes ensemble, partielles séparées
        $grouped = [];
        
        foreach ($allScans as $scan) {
            $medicament = null;
            
            if ($scan->barcode && isset($medicamentsByBarcode[$scan->barcode])) {
                $medicament = $medicamentsByBarcode[$scan->barcode];
            } elseif ($scan->commercial_name && isset($medicamentsByName[$scan->commercial_name])) {
                $medicament = $medicamentsByName[$scan->commercial_name];
            }
            
            $unite = ($medicament && $medicament->unite) ? $medicament->unite : 1;
            $isFullBox = ($scan->count == $unite);
            
            // Clé de groupement: si boîte complète, grouper par barcode+lot+expiry
            // Si partielle, ajouter l'ID pour garder séparé
            if ($isFullBox) {
                $key = $scan->barcode . '|' . $scan->lot_number . '|' . $scan->expiry_date;
            } else {
                $key = $scan->barcode . '|' . $scan->lot_number . '|' . $scan->expiry_date . '|partial_' . $scan->id;
            }
            
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'commercial_name' => $scan->commercial_name,
                    'dosage' => $scan->dosage,
                    'lot_number' => $scan->lot_number,
                    'expiry_date' => $scan->expiry_date,
                    'annee_inventaire' => $scan->annee_inventaire,
                    'barcode' => $scan->barcode,
                    'prix_2025' => 0,
                    'prix_2026' => 0,
                    'prix_2027' => 0,
                    'prix_2028' => 0,
                    'prix_2029' => 0,
                    'prix_2030' => 0,
                    'prix_total_2025' => 0,
                    'prix_total_2026' => 0,
                    'prix_total_2027' => 0,
                    'prix_total_2028' => 0,
                    'prix_total_2029' => 0,
                    'prix_total_2030' => 0,
                    'nb_scans' => 0,
                    'total_units' => 0,
                    'created_at' => $scan->created_at,
                    'box_size' => $unite,
                    'is_full_box' => $isFullBox,
                ];
            }
            
            $grouped[$key]['nb_scans']++;
            $grouped[$key]['total_units'] += $scan->count;
            
            // Prendre le prix MAX (non-null) pour chaque année
            if ($scan->prix_2025 && $scan->prix_2025 > $grouped[$key]['prix_2025']) {
                $grouped[$key]['prix_2025'] = $scan->prix_2025;
            }
            if ($scan->prix_2026 && $scan->prix_2026 > $grouped[$key]['prix_2026']) {
                $grouped[$key]['prix_2026'] = $scan->prix_2026;
            }
            if ($scan->prix_2027 && $scan->prix_2027 > $grouped[$key]['prix_2027']) {
                $grouped[$key]['prix_2027'] = $scan->prix_2027;
            }
            if ($scan->prix_2028 && $scan->prix_2028 > $grouped[$key]['prix_2028']) {
                $grouped[$key]['prix_2028'] = $scan->prix_2028;
            }
            if ($scan->prix_2029 && $scan->prix_2029 > $grouped[$key]['prix_2029']) {
                $grouped[$key]['prix_2029'] = $scan->prix_2029;
            }
            if ($scan->prix_2030 && $scan->prix_2030 > $grouped[$key]['prix_2030']) {
                $grouped[$key]['prix_2030'] = $scan->prix_2030;
            }
            
            // Calculer les prix totaux avec le prix unitaire
            $grouped[$key]['prix_total_2025'] += $scan->count * ($scan->prix_2025 ?? 0);
            $grouped[$key]['prix_total_2026'] += $scan->count * ($scan->prix_2026 ?? 0);
            $grouped[$key]['prix_total_2027'] += $scan->count * ($scan->prix_2027 ?? 0);
            $grouped[$key]['prix_total_2028'] += $scan->count * ($scan->prix_2028 ?? 0);
            $grouped[$key]['prix_total_2029'] += $scan->count * ($scan->prix_2029 ?? 0);
            $grouped[$key]['prix_total_2030'] += $scan->count * ($scan->prix_2030 ?? 0);
        }
        
        // Convertir en collection et paginer
        $collection = collect(array_values($grouped))->sortByDesc('created_at');
        
        $page = request('page', 1);
        $perPage = 20;
        $items = $collection->slice(($page - 1) * $perPage, $perPage)->values();
        
        $inventory = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $collection->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => ['annee' => $annee]]
        );
        
        // Convertir les items en objets pour garder la compatibilité
        $inventory->getCollection()->transform(function ($item) {
            return (object) $item;
        });
        
        // Calculate total inventory value and expired value
        $totalValue = 0;
        $expiredValue = 0;
        
        foreach ($inventory as $item) {
            $price = $item->$priceField ?? 0;
            $itemTotal = $item->total_units * $price;
            $totalValue += $itemTotal;
            
            if ($item->expiry_date && strtotime($item->expiry_date) < time()) {
                $expiredValue += $itemTotal;
            }
        }
        
        $valeurTotale = $totalValue;
        $valeurPerimee = $expiredValue;
        
        \Log::info('Inventory Index - Results', ['total' => $inventory->total(), 'count' => $inventory->count()]);
        
        return Inertia::render('Inventory/Index', [
            'inventory' => $inventory,
            'annee' => $annee,
            'valeurTotale' => round($valeurTotale, 2),
            'valeurPerimee' => round($valeurPerimee, 2),
        ]);
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
            
            // If no GTIN extracted and a direct barcode was submitted (linear scan), use it
            if (!$extractedBarcode && !empty($data['barcode'])) {
                $extractedBarcode = $data['barcode'];
            }
            
            \Log::info('Extracted barcode for search', ['extractedBarcode' => $extractedBarcode]);
            
            if (!$extractedBarcode) {
                return response()->json(['message' => 'Aucun code-barres détecté'], 400);
            }
            
            // Try to find medicament by extracted barcode
            [$medicament, $barcode_type] = $this->barcodeMatcher->findByBarcode($extractedBarcode);
            
            if ($medicament) {
                // Vérifier si le code scanné est un DataMatrix (contient des infos structurées GS1)
                $isDataMatrix = !empty($data['raw']) && ($parsed['gtin'] ?? null) !== null;
                
                \Log::info('Medicament found - checking DataMatrix status', [
                    'medicament_id' => $medicament->id,
                    'has_datamatrix' => $medicament->has_datamatrix,
                    'has_datamatrix_type' => gettype($medicament->has_datamatrix),
                    'isDataMatrix' => $isDataMatrix,
                    'barcode1' => $medicament->barcode1,
                ]);
                
                // Si le médicament n'a pas de DataMatrix (has_datamatrix = false), aller directement à l'entrée manuelle
                if (!$isDataMatrix && !empty($medicament->barcode1) && $medicament->has_datamatrix === false) {
                    \Log::info('Redirecting to manual entry (no_datamatrix status)');
                    return response()->json([
                        'status' => 'no_datamatrix',
                        'message' => 'Médicament trouvé - ce produit n\'a pas de DataMatrix',
                        'medicament' => [
                            'id' => $medicament->id,
                            'nom' => $medicament->nom,
                            'dosage' => $this->barcodeMatcher->extractDosage($medicament->nom),
                            'unite' => $medicament->unite ?? 1,
                        ],
                        'scanned_code' => $extractedBarcode,
                        'barcode_type' => $barcode_type,
                    ], 200);
                }
                
                // Si médicament trouvé mais code scanné n'est PAS un DataMatrix ET le médicament a un GTIN (barcode1)
                // Alors demander de scanner le DataMatrix pour avoir lot/expiry/serial
                if (!$isDataMatrix && !empty($medicament->barcode1)) {
                    \Log::info('Asking for DataMatrix scan');
                    return response()->json([
                        'status' => 'ask_datamatrix',
                        'message' => 'Médicament trouvé - veuillez scanner le DataMatrix',
                        'medicament' => [
                            'id' => $medicament->id,
                            'nom' => $medicament->nom,
                            'dosage' => $this->barcodeMatcher->extractDosage($medicament->nom),
                            'unite' => $medicament->unite ?? 1,
                        ],
                        'scanned_code' => $extractedBarcode,
                        'barcode_type' => $barcode_type,
                    ], 200);
                }
                
                // Cas 1 : Trouvé avec DataMatrix complet OU médicament sans GTIN
                return response()->json([
                    'status' => 'found',
                    'message' => 'Médicament trouvé',
                    'gtin' => $extractedBarcode,
                    'barcode_type' => $barcode_type,
                    'medicament' => [
                        'id' => $medicament->id,
                        'nom' => $medicament->nom,
                        'dosage' => $this->barcodeMatcher->extractDosage($medicament->nom),
                        'unite' => $medicament->unite ?? 1,
                    ],
                ], 200);
            }
            
            // Cas 2 : Pas trouvé localement - interroger l'API AFMPS
            \Log::info('Medication not found locally, trying AFMPS API', ['barcode' => $extractedBarcode]);
            
            $externalResult = $this->afmpsLookup->lookupByKey($extractedBarcode, 'gtin', true);
            
            if ($externalResult['commercial_name']) {
                // Médicament trouvé via API externe
                \Log::info('Medication found via AFMPS API', [
                    'barcode' => $extractedBarcode,
                    'name' => $externalResult['commercial_name'],
                    'source' => $externalResult['source'],
                ]);
                
                return response()->json([
                    'status' => 'found_external',
                    'message' => 'Médicament trouvé via AFMPS',
                    'gtin' => $extractedBarcode,
                    'barcode_type' => 'gtin',
                    'medicament' => [
                        'id' => null, // Pas d'ID local
                        'nom' => $externalResult['commercial_name'],
                        'dosage' => $externalResult['dosage'],
                    ],
                    'external_data' => $externalResult['external_data'] ?? null,
                    'source' => $externalResult['source'],
                ], 200);
            }
            
            // Cas 3 : Pas trouvé du tout - demander CNK ou autre code
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
            
            // Parse GS1 DataMatrix if raw is present
            if (!empty($data['raw'])) {
                $parsed = $this->parser->parse($data['raw']);
                \Log::info('confirmFound - Parsed GS1', ['raw' => $data['raw'], 'parsed' => $parsed]);
                
                // Merge parsed data into $data
                if (!empty($parsed['gtin'])) {
                    $data['barcode'] = $parsed['gtin'];
                }
                if (!empty($parsed['serial_number'])) {
                    $data['serial_number'] = $parsed['serial_number'];
                }
                if (!empty($parsed['expiry_date'])) {
                    $data['expiry_date'] = $parsed['expiry_date'];
                }
                if (!empty($parsed['lot_number'])) {
                    $data['lot_number'] = $parsed['lot_number'];
                }
                
                \Log::info('confirmFound - Data after merge', ['data' => $data]);
            }
            
            $extractedBarcode = $data['barcode'] ?? null;
            if (!$extractedBarcode) {
                return response()->json(['message' => 'Code-barres manquant'], 400);
            }
            
            // Use medicament_id from request (already found in scan())
            $medicamentId = $request->input('medicament_id');
            
            // Si medicament_id fourni, récupérer depuis la DB locale
            if ($medicamentId) {
                $medicament = \App\Models\Medicament::find($medicamentId);
                if (!$medicament) {
                    return response()->json(['message' => 'Médicament introuvable'], 404);
                }
                
                $commercialName = $medicament->nom;
                $dosage = $this->barcodeMatcher->extractDosage($medicament->nom);
            } else {
                // Sinon, utiliser les données externes (provenant de l'API AFMPS ou saisie manuelle)
                $commercialName = $request->input('commercial_name');
                $dosage = $request->input('dosage');
                
                if (!$commercialName) {
                    return response()->json(['message' => 'Nom commercial manquant pour médicament externe'], 400);
                }
                
                \Log::info('confirmFound - Using external medication data', [
                    'commercial_name' => $commercialName,
                    'dosage' => $dosage,
                    'barcode_type' => $data['barcode_type'] ?? 'unknown',
                ]);
                
                // Si c'est une création manuelle (barcode_type = 'manual'), créer l'entrée dans medicaments
                if (($data['barcode_type'] ?? null) === 'manual') {
                    $medicament = $this->createMedicamentFromManualInput($request, $extractedBarcode);
                    if ($medicament) {
                        $medicamentId = $medicament->id;
                        \Log::info('confirmFound - Medicament created from manual input', [
                            'medicament_id' => $medicamentId,
                            'nom' => $medicament->nom,
                        ]);
                    }
                }
            }
            
            $barcode_type = $data['barcode_type'] ?? 'datamatrix';
            
            // Si entrée manuelle du DataMatrix (manual_entry), marquer le médicament comme n'ayant pas de DataMatrix
            if ($barcode_type === 'manual_entry' && $medicamentId) {
                $medicament = \App\Models\Medicament::find($medicamentId);
                if ($medicament && $medicament->has_datamatrix !== false) {
                    $medicament->update(['has_datamatrix' => false]);
                    \Log::info('confirmFound - Marked medication as having no DataMatrix', [
                        'medicament_id' => $medicamentId,
                        'nom' => $medicament->nom,
                    ]);
                }
            }
            
            $data['user_id'] = auth()->id();
            $data['scanned_at'] = now();
            $data['barcode'] = $extractedBarcode;
            $data['barcode_type'] = $barcode_type;
            $data['commercial_name'] = $commercialName;
            $data['dosage'] = $dosage;
            $data['annee_inventaire'] = $request->input('annee_inventaire', date('Y'));
            
            // Handle quantity (for partial boxes)
            $quantity = $request->input('quantity');
            if ($quantity !== null) {
                $data['count'] = (int)$quantity;
            } else {
                $data['count'] = 1; // Default to 1 if not specified
            }
            
            $record = InventoryMedoc::create($data);
            
            return response()->json([
                'message' => 'Inventaire enregistré',
                'id' => $record->id,
                'medicament' => [
                    'nom' => $commercialName,
                    'dosage' => $dosage,
                ],
            ], 201);
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
            
            // Parser le DataMatrix original pour extraire lot, expiry, serial
            $parsedData = [];
            if (!empty($data['raw'])) {
                $parsed = $this->parser->parse($data['raw']);
                \Log::info('CNK scan - Parsed DataMatrix', ['raw' => $data['raw'], 'parsed' => $parsed]);
                
                if (!empty($parsed['serial_number'])) {
                    $parsedData['serial_number'] = $parsed['serial_number'];
                }
                if (!empty($parsed['lot_number'])) {
                    $parsedData['lot_number'] = $parsed['lot_number'];
                }
                if (!empty($parsed['expiry_date'])) {
                    $parsedData['expiry_date'] = $parsed['expiry_date'];
                }
            }
            
            // Créer l'InventoryMedoc avec toutes les données
            $recordData = array_merge($data, $parsedData, [
                'user_id' => auth()->id(),
                'scanned_at' => now(),
                'barcode' => $gtin,
                'barcode_type' => 'barcode1',
                'commercial_name' => $medicament->nom,
                'dosage' => $this->barcodeMatcher->extractDosage($medicament->nom),
                'annee_inventaire' => $request->input('annee_inventaire', date('Y'))
            ]);
            
            $record = InventoryMedoc::create($recordData);
            
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

    /**
     * Create a new Medicament entry from manual input
     */
    private function createMedicamentFromManualInput($request, string $gtin): ?Medicament
    {
        try {
            // Check if medicament already exists with this GTIN
            $existing = Medicament::where('barcode1', $gtin)->first();
            if ($existing) {
                \Log::info('Medicament already exists with this GTIN', ['gtin' => $gtin, 'id' => $existing->id]);
                return $existing;
            }
            
            $commercialName = $request->input('commercial_name');
            $dosage = $request->input('dosage', '');
            
            // Build complete name with dosage if provided
            $fullName = $commercialName;
            if (!empty($dosage)) {
                $fullName .= ' ' . $dosage;
            }
            
            // Create new medicament
            $medicament = Medicament::create([
                'nom' => $fullName,
                'barcode1' => $gtin, // GTIN
                'barcode2' => null,  // CNK will be added later if scanned
                'barcode3' => null,
                'barcode4' => null,
                'barcode5' => null,
                'barcode6' => null,
                'chargement' => 'manual_inventory_' . date('Y-m-d'), // Mark as manually created from inventory
            ]);
            
            \Log::info('Medicament created from manual input', [
                'id' => $medicament->id,
                'nom' => $medicament->nom,
                'barcode1' => $medicament->barcode1,
            ]);
            
            return $medicament;
            
        } catch (\Exception $e) {
            \Log::error('Error creating medicament from manual input: ' . $e->getMessage(), [
                'gtin' => $gtin,
                'commercial_name' => $request->input('commercial_name'),
            ]);
            return null;
        }
    }

    public function store(InventoryMedocStoreRequest $request): JsonResponse
    {
        // Legacy endpoint - use scan() instead
        return $this->scan($request);
    }

    /**
     * Show the edit prices page
     */
    public function editPrices(): Response
    {
        $annee = (int) request('annee', date('Y'));
        $priceField = "prix_$annee";
        
        // Get distinct products for the selected year
        $allItems = InventoryMedoc::select(
                'commercial_name', 
                'dosage', 
                'lot_number', 
                'expiry_date',
                'prix_2025',
                'prix_2026',
                'prix_2027',
                'prix_2028',
                'prix_2029',
                'prix_2030'
            )
            ->where('annee_inventaire', $annee)
            ->groupBy('commercial_name', 'dosage', 'lot_number', 'expiry_date', 'prix_2025', 'prix_2026', 'prix_2027', 'prix_2028', 'prix_2029', 'prix_2030')
            ->orderBy('commercial_name')
            ->get();
        
        // Separate items without price and with price for the selected year
        $itemsWithoutPrice = $allItems->filter(function($item) use ($priceField) {
            return empty($item->$priceField) || $item->$priceField == 0;
        })->values();
        
        $itemsWithPrice = $allItems->filter(function($item) use ($priceField) {
            return !empty($item->$priceField) && $item->$priceField > 0;
        })->values();
        
        // Find items without price that match a commercial_name+dosage with existing price
        $itemsWithKnownPrice = collect();
        
        foreach ($itemsWithoutPrice as $item) {
            // Look for same product (name + dosage) with a price
            $matchingItem = $itemsWithPrice->first(function($priced) use ($item) {
                return $priced->commercial_name === $item->commercial_name 
                    && $priced->dosage === $item->dosage;
            });
            
            if ($matchingItem) {
                // Clone the item and pre-fill the price
                $itemWithKnownPrice = clone $item;
                $itemWithKnownPrice->$priceField = $matchingItem->$priceField;
                $itemWithKnownPrice->prix_source = 'auto'; // Mark as auto-filled
                $itemsWithKnownPrice->push($itemWithKnownPrice);
            }
        }
        
        // Remove items with known price from itemsWithoutPrice
        $itemsWithoutPrice = $itemsWithoutPrice->filter(function($item) use ($itemsWithKnownPrice) {
            return !$itemsWithKnownPrice->contains(function($known) use ($item) {
                return $known->commercial_name === $item->commercial_name 
                    && $known->dosage === $item->dosage
                    && $known->lot_number === $item->lot_number
                    && $known->expiry_date === $item->expiry_date;
            });
        })->values();
        
        return Inertia::render('Inventory/EditPrices', [
            'itemsWithoutPrice' => $itemsWithoutPrice,
            'itemsWithKnownPrice' => $itemsWithKnownPrice,
            'itemsWithPrice' => $itemsWithPrice,
            'annee' => $annee,
        ]);
    }

    /**
     * Update prices for products
     */
    public function updatePrices(\Illuminate\Http\Request $request)
    {
        try {
            $annee = $request->input('annee');
            $items = $request->input('items', []);
            $priceField = "prix_$annee";
            
            if (!in_array($annee, [2025, 2026, 2027, 2028, 2029, 2030])) {
                return back()->with('error', 'Année invalide');
            }
            
            $updatedCount = 0;
            
            foreach ($items as $item) {
                // Normalize expiry_date to Y-m-d format
                $expiryDate = null;
                if (!empty($item['expiry_date'])) {
                    try {
                        $expiryDate = \Carbon\Carbon::parse($item['expiry_date'])->format('Y-m-d');
                    } catch (\Exception $e) {
                        \Log::warning('Invalid date format for expiry_date', ['date' => $item['expiry_date']]);
                        continue;
                    }
                }
                
                // Update all matching records in inventory
                $query = InventoryMedoc::where('annee_inventaire', $annee)
                    ->where('commercial_name', $item['commercial_name'])
                    ->where('dosage', $item['dosage'])
                    ->where('lot_number', $item['lot_number']);
                
                if ($expiryDate) {
                    $query->whereDate('expiry_date', $expiryDate);
                } else {
                    $query->whereNull('expiry_date');
                }
                
                $affected = $query->update([
                    $priceField => $item['price']
                ]);
                
                $updatedCount += $affected;
            }
            
            \Log::info('Prices updated', [
                'annee' => $annee,
                'items_count' => count($items),
                'records_updated' => $updatedCount,
            ]);
            
            return back()->with('success', "Prix mis à jour avec succès ($updatedCount enregistrements)");
            
        } catch (\Exception $e) {
            \Log::error('Error updating prices: ' . $e->getMessage());
            return back()->with('error', 'Erreur: ' . $e->getMessage());
        }
    }
}
