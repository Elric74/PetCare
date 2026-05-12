<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Medication extends Model
{
    use HasFactory;

	protected $fillable = [
		'pet_id',
		'medication_name',
		'administered_at',
		'dosage',
		'frequency',
		'reminder_date',
		'is_active',
		'administering_veterinarian',
		'notes',
	];

	protected $casts = [
		'is_active' => 'boolean',
	];

	public function pet(): BelongsTo
	{
		return $this->belongsTo(Pet::class);
	}
}