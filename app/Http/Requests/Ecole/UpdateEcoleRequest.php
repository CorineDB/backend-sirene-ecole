<?php

namespace App\Http\Requests\Ecole;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="UpdateEcoleRequest",
 *     title="Update School Request",
 *     description="Request body for updating school information",
 *     @OA\Property(
 *         property="nom",
 *         type="string",
 *         description="Name of the school",
 *         maxLength=100,
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="nom_complet",
 *         type="string",
 *         description="Full name of the school",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="reference",
 *         type="string",
 *         description="School reference code",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="telephone_contact",
 *         type="string",
 *         description="Contact phone number for the school",
 *         maxLength=20,
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="email_contact",
 *         type="string",
 *         format="email",
 *         description="Contact email for the school",
 *         maxLength=100,
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="types_etablissement",
 *         type="array",
 *         description="Array of establishment types",
 *         nullable=true,
 *         @OA\Items(
 *             type="string"
 *         )
 *     ),
 *     @OA\Property(
 *         property="responsable_nom",
 *         type="string",
 *         description="Last name of the person in charge",
 *         maxLength=255,
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="responsable_prenom",
 *         type="string",
 *         description="First name of the person in charge",
 *         maxLength=255,
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="responsable_telephone",
 *         type="string",
 *         description="Phone number of the person in charge",
 *         maxLength=20,
 *         nullable=true
 *     )
 * )
 */
class UpdateEcoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Les admins et utilisateurs avec la permission modifier_ecole peuvent modifier
        return $this->user() && (
            $this->user()->type === 'ECOLE' ||
            $this->user()->isAdmin() ||
            $this->user()->role?->permissions->pluck('slug')->contains('modifier_ecole')
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        // Pour les admins, récupérer l'ID depuis la route, sinon depuis le user
        $ecoleId = $this->route('id') ?? $this->user()->user_account_type_id;

        return [
            // Champs de la table ecoles (basés sur la migration)
            'nom' => ['sometimes', 'string', 'max:100'],
            'nom_complet' => ['sometimes', 'string'],
            'reference' => ['sometimes', 'nullable', 'string'],
            'telephone_contact' => ['sometimes', 'string', 'max:20', Rule::unique('ecoles', 'telephone_contact')->ignore($ecoleId)],
            'email_contact' => ['sometimes', 'nullable', 'email', 'max:100', Rule::unique('ecoles', 'email_contact')->ignore($ecoleId)],
            'types_etablissement' => ['sometimes', 'array'],
            'types_etablissement.*' => ['string', Rule::in(\App\Enums\TypeEtablissement::values())],

            // Informations du responsable
            'responsable_nom' => ['sometimes', 'nullable', 'string', 'max:255'],
            'responsable_prenom' => ['sometimes', 'nullable', 'string', 'max:255'],
            'responsable_telephone' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'nom.required' => 'Le nom de l\'école est requis.',
            'nom.string' => 'Le nom doit être une chaîne de caractères.',
            'nom.max' => 'Le nom ne peut pas dépasser 100 caractères.',
            'nom_complet.string' => 'Le nom complet doit être une chaîne de caractères.',
            'telephone_contact.string' => 'Le téléphone de contact doit être une chaîne de caractères.',
            'telephone_contact.max' => 'Le téléphone de contact ne peut pas dépasser 20 caractères.',
            'telephone_contact.unique' => 'Ce numéro de téléphone est déjà utilisé.',
            'email_contact.email' => 'L\'email de contact doit être une adresse email valide.',
            'email_contact.max' => 'L\'email de contact ne peut pas dépasser 100 caractères.',
            'email_contact.unique' => 'Cet email est déjà utilisé.',
            'types_etablissement.array' => 'Les types d\'établissement doivent être un tableau.',
            'types_etablissement.*.in' => 'Type d\'établissement invalide.',
            'responsable_nom.string' => 'Le nom du responsable doit être une chaîne de caractères.',
            'responsable_nom.max' => 'Le nom du responsable ne peut pas dépasser 255 caractères.',
            'responsable_prenom.string' => 'Le prénom du responsable doit être une chaîne de caractères.',
            'responsable_prenom.max' => 'Le prénom du responsable ne peut pas dépasser 255 caractères.',
            'responsable_telephone.string' => 'Le téléphone du responsable doit être une chaîne de caractères.',
            'responsable_telephone.max' => 'Le téléphone du responsable ne peut pas dépasser 20 caractères.',
        ];
    }
}
