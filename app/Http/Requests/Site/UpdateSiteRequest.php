<?php

namespace App\Http\Requests\Site;

use App\Enums\TypeEtablissement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="UpdateSiteRequest",
 *     title="Update Site Request",
 *     description="Request body for updating a site",
 *     @OA\Property(
 *         property="nom",
 *         type="string",
 *         nullable=true,
 *         description="Name of the site",
 *         maxLength=255
 *     ),
 *     @OA\Property(
 *         property="types_etablissement",
 *         type="array",
 *         nullable=true,
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
class UpdateSiteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        // Les admins peuvent modifier n'importe quel site
        if ($user->isAdmin()) {
            return true;
        }

        // Les écoles peuvent modifier uniquement leurs propres sites
        if ($user->user_account_type_type === \App\Models\Ecole::class) {
            $site = \App\Models\Site::find($this->route('id'));
            if ($site) {
                return $user->user_account_type_id === $site->ecole_principale_id;
            }
            return false;
        }

        // Autres utilisateurs: vérifier la permission
        return $user->role?->permissions->pluck('slug')->contains('modifier_site');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $siteId = $this->route('id');
        $site = \App\Models\Site::find($siteId);
        $ecoleId = $site?->ecole_principale_id;

        return [
            'nom' => [
                'sometimes',
                'string',
                'max:255',
                // Le nom doit être unique par école, sauf pour le site actuel
                Rule::unique('sites', 'nom')
                    ->where(function ($query) use ($ecoleId) {
                        return $query->where('ecole_principale_id', $ecoleId);
                    })
                    ->ignore($siteId)
            ],
            'types_etablissement' => ['sometimes', 'array'],
            'types_etablissement.*' => ['string', Rule::in(TypeEtablissement::values())],
            'responsable' => ['sometimes', 'nullable', 'string', 'max:255'],
            'adresse' => ['sometimes', 'nullable', 'string'],
            'ville_id' => ['sometimes', 'nullable', 'string', 'exists:villes,id'],
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
            'nom.string' => 'Le nom du site doit être une chaîne de caractères.',
            'nom.max' => 'Le nom du site ne peut pas dépasser 255 caractères.',
            'nom.unique' => 'Un site avec ce nom existe déjà pour cette école.',
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
