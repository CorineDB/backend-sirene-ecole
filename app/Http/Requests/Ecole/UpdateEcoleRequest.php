<?php

namespace App\Http\Requests\Ecole;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEcoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Vérifier que l'utilisateur connecté est bien de type ECOLE
        return $this->user() && $this->user()->type === 'ECOLE';
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $ecoleId = $this->user()->user_account_type_id;

        return [
            'nom' => ['sometimes', 'string', 'max:255'],
            'nom_complet' => ['sometimes', 'nullable', 'string', 'max:500'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255', Rule::unique('ecoles', 'email')->ignore($ecoleId)],
            'telephone' => ['sometimes', 'string', 'max:20', Rule::unique('ecoles', 'telephone')->ignore($ecoleId)],
            'email_contact' => ['sometimes', 'nullable', 'email', 'max:255'],
            'telephone_contact' => ['sometimes', 'nullable', 'string', 'max:20'],
            'adresse' => ['sometimes', 'nullable', 'string', 'max:500'],
            'responsable_nom' => ['sometimes', 'string', 'max:255'],
            'responsable_prenom' => ['sometimes', 'string', 'max:255'],
            'responsable_telephone' => ['sometimes', 'string', 'max:20'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'nom.string' => 'Le nom doit être une chaîne de caractères.',
            'nom.max' => 'Le nom ne peut pas dépasser 255 caractères.',
            'email.email' => 'L\'email doit être une adresse email valide.',
            'email.unique' => 'Cet email est déjà utilisé.',
            'telephone.string' => 'Le téléphone doit être une chaîne de caractères.',
            'telephone.unique' => 'Ce numéro de téléphone est déjà utilisé.',
            'latitude.numeric' => 'La latitude doit être un nombre.',
            'latitude.between' => 'La latitude doit être entre -90 et 90.',
            'longitude.numeric' => 'La longitude doit être un nombre.',
            'longitude.between' => 'La longitude doit être entre -180 et 180.',
        ];
    }
}
