<?php

namespace App\Http\Requests\Api\ModeleSirene;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateModeleSireneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Will be handled by Gate in controller
    }

    public function rules(): array
    {
        $modeleSireneId = $this->route('modeles_sirene');

        return [
            'nom' => 'sometimes|required|string|max:255',
            'reference' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('modeles_sirene', 'reference')->ignore($modeleSireneId),
            ],
            'description' => 'nullable|string',
            'specifications' => 'nullable|array',
            'version_firmware' => 'nullable|string|max:50',
            'prix_unitaire' => 'sometimes|required|numeric|min:0',
            'actif' => 'sometimes|boolean',
        ];
    }
}
