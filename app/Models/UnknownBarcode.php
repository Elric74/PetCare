<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnknownBarcode extends Model
{
    use HasFactory;

    protected $fillable = [
        'gtin',
        'barcode_scanned',
        'commercial_name',
        'dosage',
        'notes',
        'user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
