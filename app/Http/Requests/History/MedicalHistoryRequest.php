<?php

namespace App\Http\Requests\History;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class MedicalHistoryRequest extends FormRequest
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
    public function rules()
    {
        return [
            'histories' => ['required', 'array'],
            'histories.*.condition' => ['required', 'string', 'in:Visite,Opération,Urgence'],
            'histories.*.diagnosis_date' => ['required', 'date'],
            'histories.*.treatment' => ['nullable', 'string'],
            'histories.*.weight_g' => ['nullable', 'integer', 'min:0'],
            'histories.*.notes' => ['nullable', 'string'],
            'histories.*.structured_notes' => ['nullable', 'array'],
            'histories.*.structured_notes.yeux_oreilles' => ['nullable', 'string', 'max:20000'],
            'histories.*.structured_notes.bouche' => ['nullable', 'string', 'max:20000'],
            'histories.*.structured_notes.coeur' => ['nullable', 'string', 'max:20000'],
            'histories.*.structured_notes.mobilite' => ['nullable', 'string', 'max:20000'],
            'histories.*.structured_notes.peau' => ['nullable', 'string', 'max:20000'],
            'histories.*.structured_notes.autre' => ['nullable', 'string', 'max:20000'],
        ];
    }

    public function messages()
    {
        return [
            'histories.required' => 'The histories field is required.',
            'histories.*.condition.required' => 'The condition name is required.',
            'histories.*.diagnosis_date.date' => 'The diagnosis date must be a valid date.',
        ];
    }
}