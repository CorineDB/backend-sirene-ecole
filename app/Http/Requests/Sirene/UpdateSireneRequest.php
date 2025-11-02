<?php

namespace App\Http\Requests\Sirene;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSireneRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Admin ou technicien peuvent modifier
        return $this->user() && in_array($this->user()->type, ['ADMIN', 'TECHNICIEN']);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'modele_id' => ['sometimes', 'string', 'exists:modeles_sirene,id'],
            'etat' => ['sometimes', 'string', 'in:NEUF,BON,MOYEN,MAUVAIS,HORS_SERVICE'],
            'statut' => ['sometimes', 'string'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'date_fabrication' => ['sometimes', 'date', 'before_or_equal:today'],
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'modele_id.exists' => 'Le modèle de sirène sélectionné n\'existe pas.',
            'etat.in' => 'L\'état doit être: NEUF, BON, MOYEN, MAUVAIS ou HORS_SERVICE.',
            'date_fabrication.date' => 'La date de fabrication doit être une date valide.',
            'date_fabrication.before_or_equal' => 'La date de fabrication ne peut pas être dans le futur.',
        ];
    }
}
