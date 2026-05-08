<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryMedoc extends Model
{
    use HasFactory;

    protected $table = 'inventaire_medoc';

    protected $fillable = [
        'barcode',
        'barcode_type',
        'serial_number',
        'expiry_date',
        'lot_number',
        'raw',
        'scanned_at',
        'user_id',
        'annee_inventaire',
        'commercial_name',
        'dosage',
        'count',
        'prix_2025',
        'prix_2026',
        'prix_2027',
        'prix_2028',
        'prix_2029',
        'prix_2030',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'scanned_at' => 'datetime',
    ];
}
