<?php

namespace App\Http\Requests\Pet;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class PetStoreRequest extends FormRequest
{
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
            'breed_id' => ['nullable', 'integer'],
            //'age' removed in favor of birth_date
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', 'string', 'max:255', \Illuminate\Validation\Rule::in(['Femelle','Male','À déterminer'])],
            'is_sterilized' => ['nullable', 'boolean'],
            'sterilized_at' => ['nullable', 'date', 'before_or_equal:today'],
            'chip_number' => ['nullable', 'string', 'max:255', 'unique:pets,chip_number'],
            'client_id' => ['required', 'integer'],
            // allow HEIC/HEIF and increase max to 4MB
            // Use 'file' instead of 'image' because PHP's image detection may not recognise HEIC/HEIF
            'photo' => ['nullable', 'file', 'mimes:jpeg,png,jpg,heic,heif', 'max:4096'],
            'decedee' => ['nullable', 'boolean'],
            'date_deces' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }
}