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
        'commercial_name',
        'dosage',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'scanned_at' => 'datetime',
    ];
}
