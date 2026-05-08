<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Medicament extends Model
{
    use HasFactory;

    protected $fillable = [
        'chargement',
        'barcode1',      // GTIN (DataMatrix)
        'barcode2',      // CNK (Belgian code)
        'barcode3',
        'barcode4',
        'barcode5',
        'barcode6',
        'has_datamatrix', // Boolean: does this medication have a DataMatrix?
        'unite',         // Number of sellable units in package
        'nom',
        'forme_pharmaceutique',
        'voie_administration',
        'firme',
        'commercialise',
        'probleme_disponibilite',
        'especes_cibles',
        'temps_attente',
        'substance_active',
        'code_atc',
        'usage',
        'url_notice_nl',
        'url_notice_fr',
        'url_notice_de',
        'url_skp',
        'url_rcp',
        'url_zma_zmt',
        'url_rma_nl',
        'url_rma_fr',
        'url_rma_de',
        'url_dhcp_nl',
        'url_dhcp_fr',
        'url_dhpc_de',
        'url_summary_rmp_nl',
        'url_summary_rmp_fr',
        'date_publication_rcp',
        'date_publication_rma',
        'date_publication_dhpc',
        'date_publication_summary_rmp',
        'date_approbation_rcp',
        'date_approbation_rma',
        'date_approbation_dhpc',
        'date_approbation_summary_rmp',
    ];

    protected $casts = [
        'has_datamatrix' => 'boolean',
        'unite' => 'integer',
        'date_publication_rcp' => 'date',
        'date_publication_rma' => 'date',
        'date_publication_dhpc' => 'date',
        'date_publication_summary_rmp' => 'date',
        'date_approbation_rcp' => 'date',
        'date_approbation_rma' => 'date',
        'date_approbation_dhpc' => 'date',
        'date_approbation_summary_rmp' => 'date',
    ];
}
