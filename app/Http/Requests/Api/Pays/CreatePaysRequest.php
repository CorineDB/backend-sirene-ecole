<?php

namespace App\Http\Requests\Api\Pays;

use Illuminate\Foundation\Http\FormRequest;

class CreatePaysRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Will be handled by Gate in controller
    }

    public function rules(): array
    {
        return [
            'nom' => 'required|string|max:255|unique:pays,nom',
            'code_iso' => 'required|string|max:2',
            'indicatif_tel' => 'required|string|max:10',
            'devise' => 'nullable|string|max:3',
            'fuseau_horaire' => 'nullable|string|max:255',
            'actif' => 'sometimes|boolean',
        ];
    }
}
