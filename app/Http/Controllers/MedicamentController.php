<?php

namespace App\Http\Controllers;

use App\Models\Medicament;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class MedicamentController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $query = Medicament::query();
        if ($q !== '') {
            $query->where(function ($qb) use ($q) {
                $qb->where('nom', 'like', "%$q%")
                   ->orWhere('barcode1', 'like', "%$q%")
                   ->orWhere('barcode2', 'like', "%$q%")
                   ->orWhere('barcode3', 'like', "%$q%")
                   ->orWhere('barcode4', 'like', "%$q%")
                   ->orWhere('barcode5', 'like', "%$q%")
                   ->orWhere('barcode6', 'like', "%$q%");
            });
        }
        $medicaments = $query->select(['id','barcode1','barcode2','barcode3','barcode4','barcode5','barcode6','nom','substance_active','code_atc'])
            ->orderBy('nom')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Medicaments/Index', [
            'medicaments' => $medicaments,
            'q' => $q,
        ]);
    }
    
    public function edit(Medicament $medicament): Response
    {
        return Inertia::render('Medicaments/Edit', [
            'medicament' => $medicament,
        ]);
    }

    public function update(Request $request, Medicament $medicament): JsonResponse
    {
        $data = $request->validate([
            'barcode1' => ['nullable','string','max:255'],
            'barcode2' => ['nullable','string','max:255'],
            'barcode3' => ['nullable','string','max:255'],
            'barcode4' => ['nullable','string','max:255'],
            'barcode5' => ['nullable','string','max:255'],
            'barcode6' => ['nullable','string','max:255'],
            'unite' => ['nullable','integer','min:1'],
            'nom' => ['nullable','string','max:255'],
            'forme_pharmaceutique' => ['nullable','string','max:255'],
            'voie_administration' => ['nullable','string','max:255'],
            'firme' => ['nullable','string','max:255'],
            'commercialise' => ['nullable','string','max:255'],
            'probleme_disponibilite' => ['nullable','string','max:255'],
            'especes_cibles' => ['nullable','string'],
            'temps_attente' => ['nullable','string'],
            'substance_active' => ['nullable','string'],
            'code_atc' => ['nullable','string','max:255'],
            'usage' => ['nullable','string','max:255'],
            'url_notice_nl' => ['nullable','string'],
            'url_notice_fr' => ['nullable','string'],
            'url_notice_de' => ['nullable','string'],
            'url_skp' => ['nullable','string'],
            'url_rcp' => ['nullable','string'],
            'url_zma_zmt' => ['nullable','string'],
            'url_rma_nl' => ['nullable','string'],
            'url_rma_fr' => ['nullable','string'],
            'url_rma_de' => ['nullable','string'],
            'url_dhcp_nl' => ['nullable','string'],
            'url_dhcp_fr' => ['nullable','string'],
            'url_dhpc_de' => ['nullable','string'],
            'url_summary_rmp_nl' => ['nullable','string'],
            'url_summary_rmp_fr' => ['nullable','string'],
            'date_publication_rcp' => ['nullable','date'],
            'date_publication_rma' => ['nullable','date'],
            'date_publication_dhpc' => ['nullable','date'],
            'date_publication_summary_rmp' => ['nullable','date'],
            'date_approbation_rcp' => ['nullable','date'],
            'date_approbation_rma' => ['nullable','date'],
            'date_approbation_dhpc' => ['nullable','date'],
            'date_approbation_summary_rmp' => ['nullable','date'],
        ]);
        $medicament->update($data);
        return response()->json(['message' => 'Médicament mis à jour']);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'barcode1' => ['nullable','string','max:255'],
            'barcode2' => ['nullable','string','max:255'],
            'barcode3' => ['nullable','string','max:255'],
            'barcode4' => ['nullable','string','max:255'],
            'barcode5' => ['nullable','string','max:255'],
            'barcode6' => ['nullable','string','max:255'],
            'nom' => ['nullable','string','max:255'],
            'forme_pharmaceutique' => ['nullable','string','max:255'],
        ]);
        $data['chargement'] = 'manuel';
        $medicament = Medicament::create($data);
        return response()->json(['message' => 'Médicament créé', 'id' => $medicament->id], 201);
    }

    public function linkGtinCnk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'barcode1' => ['required','string'],
            'barcode2' => ['required','string'],
        ]);

        // Try to find by CNK (barcode2) first; if exists, update GTIN (barcode1)
        $med = Medicament::where('barcode2', $data['barcode2'])->first();
        if ($med) {
            $med->barcode1 = $data['barcode1'];
            $med->save();
            return response()->json(['message' => 'Lien CNK↔GTIN mis à jour', 'id' => $med->id], 200);
        }

        // Else try find by GTIN, update CNK
        $med = Medicament::where('gtin', $data['gtin'])->first();
        if ($med) {
            $med->cnk = $data['cnk'];
            $med->save();
            return response()->json(['message' => 'Lien CNK↔GTIN mis à jour', 'id' => $med->id], 200);
        }

        // Else create a minimal record with both
        $med = Medicament::create([
            'gtin' => $data['gtin'],
            'cnk' => $data['cnk'],
            'nom' => null,
        ]);
        return response()->json(['message' => 'Lien CNK↔GTIN créé', 'id' => $med->id], 201);
    }
}
