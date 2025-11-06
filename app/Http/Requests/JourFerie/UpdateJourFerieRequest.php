<?php

namespace App\Http\Requests\JourFerie;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="UpdateJourFerieRequest",
 *     title="Update Public Holiday Request",
 *     description="Request body for updating an existing public holiday entry",
 *     @OA\Property(
 *         property="calendrier_id",
 *         type="string",
 *         format="uuid",
 *         nullable=true,
 *         description="ID of the school calendar this holiday belongs to"
 *     ),
 *     @OA\Property(
 *         property="ecole_id",
 *         type="string",
 *         format="uuid",
 *         nullable=true,
 *         description="ID of the school this holiday belongs to (if specific to a school)"
 *     ),
 *     @OA\Property(
 *         property="pays_id",
 *         type="string",
 *         format="uuid",
 *         nullable=true,
 *         description="ID of the country this holiday belongs to (if global for a country)"
 *     ),
 *     @OA\Property(
 *         property="libelle",
 *         type="string",
 *         nullable=true,
 *         description="Label for the holiday (alternative to nom)"
 *     ),
 *     @OA\Property(
 *         property="nom",
 *         type="string",
 *         nullable=true,
 *         description="Name of the public holiday"
 *     ),
 *     @OA\Property(
 *         property="date",
 *         type="string",
 *         format="date",
 *         nullable=true,
 *         description="Date of the public holiday"
 *     ),
 *     @OA\Property(
 *         property="type",
 *         type="string",
 *         nullable=true,
 *         description="Type of holiday (e.g., national, religious, local)"
 *     ),
 *     @OA\Property(
 *         property="recurrent",
 *         type="boolean",
 *         nullable=true,
 *         description="Is this holiday recurrent every year?"
 *     ),
 *     @OA\Property(
 *         property="actif",
 *         type="boolean",
 *         nullable=true,
 *         description="Is this holiday active?"
 *     )
 * )
 */
class UpdateJourFerieRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Adjust authorization logic as needed
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'calendrier_id' => ['sometimes', 'nullable', 'string', 'exists:calendriers_scolaires,id'],
            'ecole_id' => ['sometimes', 'nullable', 'string', 'exists:ecoles,id'],
            'pays_id' => ['sometimes', 'nullable', 'string', 'exists:pays,id'],
            'intitule_journee' => ['sometimes', 'required', 'string'],
            'date' => ['sometimes', 'required', 'date'],
            'recurrent' => ['sometimes', 'boolean'],
            'actif' => ['sometimes', 'boolean'],
            'est_national' => ['sometimes', 'boolean'],
        ];
    }
}