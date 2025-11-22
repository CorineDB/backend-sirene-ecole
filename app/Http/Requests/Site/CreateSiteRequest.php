<?php

namespace App\Http\Requests\Site;

use App\Enums\TypeEtablissement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="CreateSiteRequest",
 *     title="Create Site Request",
 *     description="Request body for creating a new site (annexe)",
 *     required={"ecole_principale_id", "nom", "types_etablissement"},
 *     @OA\Property(
 *         property="ecole_principale_id",
 *         type="string",
 *         format="ulid",
 *         description="ID of the principal school"
 *     ),
 *     @OA\Property(
 *         property="nom",
 *         type="string",
 *         description="Name of the site",
 *         maxLength=255
 *     ),
 *     @OA\Property(
 *         property="types_etablissement",
 *         type="array",
 *         description="Array of establishment types",
 *         @OA\Items(type="string")
 *     ),
 *     @OA\Property(
 *         property="responsable",
 *         type="string",
 *         nullable=true,
 *         description="Person in charge of the site",
 *         maxLength=255
 *     ),
 *     @OA\Property(
 *         property="adresse",
 *         type="string",
 *         nullable=true,
 *         description="Address of the site"
 *     ),
 *     @OA\Property(
 *         property="ville_id",
 *         type="string",
 *         format="ulid",
 *         nullable=true,
 *         description="ID of the city"
 *     ),
 *     @OA\Property(
 *         property="latitude",
 *         type="number",
 *         format="float",
 *         nullable=true,
 *         description="Latitude coordinate",
 *         minimum=-90,
 *         maximum=90
 *     ),
 *     @OA\Property(
 *         property="longitude",
 *         type="number",
 *         format="float",
 *         nullable=true,
 *         description="Longitude coordinate",
 *         minimum=-180,
 *         maximum=180
 *     )
 * )
 */
class CreateSiteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        // Les admins peuvent créer des sites pour n'importe quelle école
        if ($user->isAdmin()) {
            return true;
        }

        // Les écoles peuvent créer des sites uniquement pour elles-mêmes
        if ($user->user_account_type_type === \App\Models\Ecole::class) {
            $ecoleId = $this->input('ecole_principale_id');
            return $user->user_account_type_id === $ecoleId;
        }

        // Autres utilisateurs: vérifier la permission
        return $user->role?->permissions->pluck('slug')->contains('creer_site');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'ecole_principale_id' => ['required', 'string', 'exists:ecoles,id'],
            'nom' => [
                'required',
                'string',
                'max:255',
                // Le nom doit être unique par école
                Rule::unique('sites', 'nom')->where(function ($query) {
                    return $query->where('ecole_principale_id', $this->input('ecole_principale_id'));
                })
            ],
            'types_etablissement' => ['required', 'array'],
            'types_etablissement.*' => ['string', Rule::in(TypeEtablissement::values())],
            'responsable' => ['nullable', 'string', 'max:255'],
            'adresse' => ['nullable', 'string'],
            'ville_id' => ['nullable', 'string', 'exists:villes,id'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Auto-assigner ecole_principale_id si l'utilisateur est une école
        $user = $this->user();
        if ($user && $user->user_account_type_type === \App\Models\Ecole::class) {
            if (!$this->has('ecole_principale_id')) {
                $this->merge([
                    'ecole_principale_id' => $user->user_account_type_id,
                ]);
            }
        }

        // Les sites créés via cette requête sont toujours des sites annexes
        $this->merge([
            'est_principale' => false,
        ]);
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'ecole_principale_id.required' => 'L\'ID de l\'école principale est requis.',
            'ecole_principale_id.exists' => 'L\'école spécifiée n\'existe pas.',
            'nom.required' => 'Le nom du site est requis.',
            'nom.string' => 'Le nom du site doit être une chaîne de caractères.',
            'nom.max' => 'Le nom du site ne peut pas dépasser 255 caractères.',
            'nom.unique' => 'Un site avec ce nom existe déjà pour cette école.',
            'types_etablissement.required' => 'Les types d\'établissement sont requis.',
            'types_etablissement.array' => 'Les types d\'établissement doivent être un tableau.',
            'types_etablissement.*.in' => 'Type d\'établissement invalide.',
            'responsable.string' => 'Le responsable doit être une chaîne de caractères.',
            'responsable.max' => 'Le responsable ne peut pas dépasser 255 caractères.',
            'adresse.string' => 'L\'adresse doit être une chaîne de caractères.',
            'ville_id.exists' => 'La ville spécifiée n\'existe pas.',
            'latitude.numeric' => 'La latitude doit être un nombre.',
            'latitude.between' => 'La latitude doit être entre -90 et 90.',
            'longitude.numeric' => 'La longitude doit être un nombre.',
            'longitude.between' => 'La longitude doit être entre -180 et 180.',
        ];
    }
}
