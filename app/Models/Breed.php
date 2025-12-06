<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use \Illuminate\Database\Eloquent\Relations\BelongsTo;

class Breed extends Model
{
    use HasFactory;

	protected $table = 'breeds';

	protected $fillable = [
		'name',
		'species_id',
		'photo_path'
	];

	public function species(): BelongsTo
	{
		return $this->belongsTo(Species::class);
	}

}