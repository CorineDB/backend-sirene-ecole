<?php

namespace App\Http\Requests\Technicien;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *     schema="UpdateTechnicienRequest",
 *     title="Update Technicien Request",
 *     description="Request body for updating an existing technician",
 *     @OA\Property(
 *         property="user",
 *         type="object",
 *         @OA\Property(property="nom_utilisateur", type="string", maxLength=255, description="Username"),
 *         @OA\Property(property="identifiant", type="string", maxLength=255, description="Unique identifier"),
 *         @OA\Property(property="mot_de_passe", type="string", minLength=8, description="Password"),
 *         @OA\Property(property="type", type="string", enum={"TECHNICIEN"}, description="User type"),
 *         @OA\Property(property="role_id", type="string", format="uuid", nullable=true, description="ID of the role"),
 *     ),
 *     @OA\Property(property="ville_id", type="string", format="uuid", description="ID of the city (ville d'affectation)"),
 *     @OA\Property(property="specialite", type="string", maxLength=255, description="Technician's specialty"),
 *     @OA\Property(property="disponibilite", type="boolean", description="Technician's availability"),
 *     @OA\Property(property="date_embauche", type="string", format="date", nullable=true, description="Date of hire"),
 * )
 */
class UpdateTechnicienRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->type === 'ADMIN'; // Only admin can update technicians
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        // Get technicien ID from route and load the technicien with user relation
        $technicienId = $this->route('id');
        $technicien = \App\Models\Technicien::with('user')->find($technicienId);

        // Get user_id if technicien and user exist
        $userId = $technicien?->user?->id;

        return [
            'user.nom_utilisateur' => ['sometimes', 'string', 'max:255'],

            'ville_id' => ['sometimes', 'string', 'exists:villes,id'], // Ville d'affectation du technicien
            'specialite' => ['sometimes', 'string', 'max:255'],
            'disponibilite' => ['boolean'],
            'date_embauche' => ['nullable', 'date'],

            // UserInfo related fields (assuming they come in the same request)
            'user.userInfoData.email' => ['sometimes', 'string', 'email', 'max:255', $userId ? Rule::unique('user_infos', 'email')->ignore($userId, 'user_id') : 'unique:user_infos,email'],
            'user.userInfoData.telephone' => ['sometimes', 'string', 'max:20', $userId ? Rule::unique('user_infos', 'telephone')->ignore($userId, 'user_id') : 'unique:user_infos,telephone'],
            'user.userInfoData.prenom' => ['sometimes', 'string', 'max:255'],
            'user.userInfoData.nom' => ['sometimes', 'string', 'max:255'],
            'user.userInfoData.adresse' => ['sometimes', 'string', 'max:255'],
            'user.userInfoData.ville_id' => ['sometimes', 'string', 'exists:villes,id']
        ];
    }
}
