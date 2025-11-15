<?php

namespace App\Http\Requests\Api\Pays;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaysRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Will be handled by Gate in controller
    }

    public function rules(): array
    {
        $paysId = $this->route('pay'); // Assuming route parameter is named 'pay'

        return [
            'nom' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('pays', 'nom')->ignore($paysId),
            ],
            'code_iso' => 'sometimes|required|string|max:2',
            'indicatif_tel' => 'sometimes|required|string|max:10',
            'devise' => 'nullable|string|max:3',
            'fuseau_horaire' => 'nullable|string|max:255',
            'actif' => 'sometimes|boolean',
        ];
    }
}
