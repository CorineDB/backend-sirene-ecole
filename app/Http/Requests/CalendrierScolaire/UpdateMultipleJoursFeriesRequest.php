<?php

namespace App\Http\Requests\CalendrierScolaire;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="UpdateMultipleJoursFeriesRequest",
 *     title="Update Multiple Public Holidays Request",
 *     description="Request body for updating multiple public holiday entries for a school calendar",
 *     type="array",
 *     @OA\Items(
 *         @OA\Property(
 *             property="id",
 *             type="string",
 *             format="uuid",
 *             description="ID of the public holiday (required for updates)"
 *         ),
 *         @OA\Property(
 *             property="intitule_journee",
 *             type="string",
 *             description="Title of the public holiday"
 *         ),
 *         @OA\Property(
 *             property="date",
 *             type="string",
 *             format="date",
 *             description="Date of the public holiday"
 *         ),
 *         @OA\Property(
 *             property="recurrent",
 *             type="boolean",
 *             description="Is this holiday recurrent every year?"
 *         ),
 *         @OA\Property(
 *             property="actif",
 *             type="boolean",
 *             description="Is this holiday active?",
 *             default=true
 *         ),
 *         @OA\Property(
 *             property="est_national",
 *             type="boolean",
 *             description="Is this a national holiday?",
 *             default=false
 *         )
 *     )
 * )
 */
class UpdateMultipleJoursFeriesRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            '*.id' => ['required', 'string', 'exists:jours_feries,id'],
            '*.intitule_journee' => ['required', 'string'],
            '*.date' => ['required', 'date'],
            '*.recurrent' => ['required', 'boolean'],
            '*.actif' => ['boolean'],
            '*.est_national' => ['boolean'],
        ];
    }
}
