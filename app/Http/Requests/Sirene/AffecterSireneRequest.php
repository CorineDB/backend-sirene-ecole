<?php

namespace App\Http\Requests\Sirene;

use Illuminate\Foundation\Http\FormRequest;

class AffecterSireneRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Admin ou technicien peuvent affecter
        return $this->user() && in_array($this->user()->type, ['ADMIN', 'TECHNICIEN']);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'site_id' => ['required', 'string', 'exists:sites,id'],
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'site_id.required' => 'Le site est requis.',
            'site_id.exists' => 'Le site sélectionné n\'existe pas.',
        ];
    }
}
