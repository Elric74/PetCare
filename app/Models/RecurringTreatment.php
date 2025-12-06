<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringTreatment extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'periodicity',
        'species_id'
    ];

    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class);
    }
}
