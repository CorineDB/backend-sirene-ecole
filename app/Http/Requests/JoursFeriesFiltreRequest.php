<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class JoursFeriesFiltreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'est_national' => 'sometimes|boolean',
            'ecole_id' => 'sometimes|string|exists:ecoles,id',
            'date_debut' => 'sometimes|date',
            'date_fin' => 'sometimes|date|after_or_equal:date_debut',
        ];
    }

    /**
     * Récupère les filtres validés de la requête.
     *
     * @return array
     */
    public function getFiltres(): array
    {
        return $this->validated();
    }
}
