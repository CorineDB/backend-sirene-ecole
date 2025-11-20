<?php

namespace App\Http\Requests\Technicien;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *     schema="CreateTechnicienRequest",
 *     title="Create Technicien Request",
 *     description="Request body for creating a new technician",
 *     required={
 *         "user",
 *         "ville_id",
 *         "specialite"
 *     },
 *     @OA\Property(
 *         property="user",
 *         type="object",
 *         required={
 *             "nom_utilisateur",
 *             "identifiant",
 *             "mot_de_passe",
 *             "type"
 *         },
 *         @OA\Property(property="nom_utilisateur", type="string", maxLength=255, description="Username"),
 *         @OA\Property(property="identifiant", type="string", maxLength=255, description="Unique identifier"),
 *         @OA\Property(property="mot_de_passe", type="string", minLength=8, description="Password"),
 *         @OA\Property(property="type", type="string", enum={"TECHNICIEN"}, description="User type"),
 *         @OA\Property(property="role_id", type="string", format="uuid", nullable=true, description="ID of the role"),
 *     ),
 *     @OA\Property(property="ville_id", type="string", format="uuid", description="ID of the city"),
 *     @OA\Property(property="specialite", type="string", maxLength=255, description="Technician's specialty"),
 *     @OA\Property(property="disponibilite", type="boolean", description="Technician's availability"),
 *     @OA\Property(property="date_embauche", type="string", format="date", nullable=true, description="Date of hire"),
 * )
 */
class CreateTechnicienRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->type === 'ADMIN'; // Only admin can create technicians
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [

            // UserInfo related fields (assuming they come in the same request)
            'user.userInfoData.email' => ['nullable', 'string', 'email', 'max:255', 'unique:user_infos,email'],
            'user.userInfoData.telephone' => ['required', 'string', 'max:20', 'unique:user_infos,telephone'],
            'user.userInfoData.prenom' => ['nullable', 'string', 'max:255'],
            'user.userInfoData.nom' => ['required', 'string', 'max:255'],
            'user.userInfoData.adresse' => ['nullable', 'string', 'max:255'],
            'user.userInfoData.ville_id' => ['nullable', 'string', 'exists:villes,id'],

            'ville_id' => ['required', 'string', 'exists:villes,id'], // Ville d'affectation du technicien
            'specialite' => ['required', 'string', 'max:255'],
            'disponibilite' => ['boolean'],
            'date_embauche' => ['nullable', 'date'],
        ];
    }
}
