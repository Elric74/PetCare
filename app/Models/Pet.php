<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Carbon\Carbon;

class Pet extends Model
{
	use HasFactory;

	protected $fillable = [
		'name',
		'species_id',
		'breed_id',
		'birth_date',
		'gender',
		'is_sterilized',
		'sterilized_at',
		'decedee',
		'date_deces',
		'chip_number',
		'tag',
		'client_id',
		'photo'
	];

	protected $casts = [
		'is_sterilized' => 'boolean',
		'sterilized_at' => 'date',
	];

	protected $appends = ['age_years_months'];

	protected function performInsert(Builder $query)
	{
		$this->slug = Str::slug($this->name) . '-temp';
		parent::performInsert($query);
	}

	protected static function booted()
	{
		static::created(function ($pet) {
			$pet->slug = Str::slug($pet->name) . '-' . $pet->id;
			$pet->save();
		});
	}

	public function client(): BelongsTo
	{
		return $this->belongsTo(Client::class);
	}

	public function species(): BelongsTo
	{
		return $this->BelongsTo(Species::class);
	}

	public function breed(): BelongsTo
	{
		return $this->BelongsTo(Breed::class);
	}

		public function vaccinations(): HasMany
		{
			return $this->hasMany(Vaccination::class);
		}

		public function medicalHistory(): HasMany
		{
			return $this->hasMany(MedicalHistory::class);
		}

		public function medications(): HasMany
		{
			return $this->hasMany(Medication::class);
		}

		public function surgicalHistory(): HasMany
		{
			return $this->hasMany(SurgicalHistory::class);
		}

		public function images()
		{
			return $this->hasMany(Image::class);
		}

		public function mailboxMessages(): HasMany
		{
			return $this->hasMany(MailboxMessage::class);
		}

		public function getAgeYearsMonthsAttribute()
		{
			if (! $this->birth_date) {
				return null;
			}

			$birth = Carbon::parse($this->birth_date);
			$now = Carbon::now();
			$years = $birth->diffInYears($now);
			$months = $birth->copy()->addYears($years)->diffInMonths($now);

			$parts = [];
			if ($years > 0) {
				$parts[] = $years . ' year' . ($years > 1 ? 's' : '');
			}
			if ($months > 0) {
				$parts[] = $months . ' month' . ($months > 1 ? 's' : '');
			}

			return count($parts) ? implode(' / ', $parts) : '0 month';
		}
}