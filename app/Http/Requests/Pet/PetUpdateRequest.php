<?php

namespace App\Http\Requests\Pet;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PetUpdateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('gender') && is_string($this->input('gender'))) {
            $gender = trim($this->input('gender'));
            // Normalize DB variations
            if ($gender === 'à déterminer' || $gender === 'À déterminer') {
                $this->merge(['gender' => 'À déterminer']);
            }
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'species_id' => ['required', 'integer'],
            'breed_id' => ['sometimes', 'integer'],
            //'age' removed in favor of birth_date
            'birth_date' => ['sometimes', 'date', 'before_or_equal:today'],
            'gender' => ['sometimes', 'string', 'max:255', \Illuminate\Validation\Rule::in([
                'Femelle Stérilisée',
                'Femelle',
                'Male',
                'Male castré',
                // Legacy values kept for backward compatibility
                'À déterminer',
                'à déterminer',
            ])],
            'is_sterilized' => ['sometimes', 'boolean'],
            'sterilized_at' => ['sometimes', 'date', 'before_or_equal:today'],
            'chip_number' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('pets', 'chip_number')->ignore($this->route('id')),
            ],
            'tag' => ['nullable', 'integer', 'in:1,2,3'],
            'client_id' => ['required', 'integer'],
            // allow HEIC/HEIF and increase max to 4MB
            // Use 'file' instead of 'image' because PHP's image detection may not recognise HEIC/HEIF
            'photo' => ['nullable', 'file', 'mimes:jpeg,png,jpg,heic,heif', 'max:4096'],
            'decedee' => ['sometimes', 'boolean'],
            'date_deces' => ['sometimes', 'date', 'before_or_equal:today'],
        ];
    }
}