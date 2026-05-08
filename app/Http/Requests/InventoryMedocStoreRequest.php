<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class InventoryMedocStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function prepareForValidation(): void
    {
        // Clean control characters from input before validation
        $cleaned = [];
        foreach ($this->all() as $key => $value) {
            if (is_string($value)) {
                // Remove all control characters (ASCII 0-31 and 127)
                $cleaned[$key] = preg_replace('/[\x00-\x1F\x7F]/', '', $value);
            } else {
                $cleaned[$key] = $value;
            }
        }
        $this->merge($cleaned);
    }

    public function rules(): array
    {
        return [
            'barcode' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'expiry_date' => ['nullable', 'date'],
            'lot_number' => ['nullable', 'string', 'max:255'],
            'raw' => ['nullable', 'string', 'max:1000'],
            'medicament_id' => ['nullable', 'integer', 'exists:medicaments,id'],
            'barcode_type' => ['nullable', 'string', 'max:50'],
        ];
    }
}

