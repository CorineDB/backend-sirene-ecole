<?php

namespace App\Http\Requests\Api\Ville;

use Illuminate\Foundation\Http\FormRequest;

class CreateVilleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Will be handled by Gate in controller
    }

    public function rules(): array
    {
        return [
            'pays_id' => 'required|exists:pays,id',
            'nom' => 'required|string|max:255',
            'code' => 'nullable|string|max:10',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'actif' => 'sometimes|boolean',
        ];
    }
}
