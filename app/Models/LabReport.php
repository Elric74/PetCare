<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LabReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'zoolyx_report_id',
        'pet_id',
        'pet_name',
        'pet_species',
        'owner_first_name',
        'owner_last_name',
        'reception_date',
        'updated_date',
        'pdf_path',
    ];

    protected $casts = [
        'reception_date' => 'date',
        'updated_date' => 'date',
    ];

    public function pet()
    {
        return $this->belongsTo(Pet::class);
    }
}
