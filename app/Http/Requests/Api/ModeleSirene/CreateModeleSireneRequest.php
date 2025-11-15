<?php

namespace App\Http\Requests\Api\ModeleSirene;

use Illuminate\Foundation\Http\FormRequest;

class CreateModeleSireneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Will be handled by Gate in controller
    }

    public function rules(): array
    {
        return [
            'nom' => 'required|string|max:255',
            'reference' => 'required|string|max:255|unique:modeles_sirene,reference',
            'description' => 'nullable|string',
            'specifications' => 'nullable|array',
            'version_firmware' => 'nullable|string|max:50',
            'prix_unitaire' => 'required|numeric|min:0',
            'actif' => 'sometimes|boolean',
        ];
    }
}
