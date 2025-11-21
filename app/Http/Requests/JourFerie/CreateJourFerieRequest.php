<?php

namespace App\Http\Requests\JourFerie;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="CreateJourFerieRequest",
 *     title="Create Public Holiday Request",
 *     description="Request body for creating a new public holiday entry",
 *     required={"nom", "date", "recurrent"},
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
 *         description="Name of the public holiday"
 *     ),
 *     @OA\Property(
 *         property="date",
 *         type="string",
 *         format="date",
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
 *         description="Is this holiday recurrent every year?"
 *     ),
 *     @OA\Property(
 *         property="actif",
 *         type="boolean",
 *         description="Is this holiday active?",
 *         default=true
 *     )
 * )
 */
class CreateJourFerieRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Adjust authorization logic as needed
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('date')) {
            $this->merge([
                'date' => $this->parseDate($this->date),
            ]);
        }
    }

    /**
     * Parse date from multiple formats (Y-m-d or d/m/Y) to Y-m-d.
     */
    private function parseDate(?string $date): ?string
    {
        if (!$date) {
            return null;
        }

        // Already in Y-m-d format
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        // Convert from d/m/Y format
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date)) {
            return \Carbon\Carbon::createFromFormat('d/m/Y', $date)->format('Y-m-d');
        }

        return $date;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'calendrier_id' => ['nullable', 'string', 'exists:calendriers_scolaires,id'],
            'ecole_id' => ['nullable', 'string', 'exists:ecoles,id'],
            'pays_id' => ['nullable', 'string', 'exists:pays,id'],
            'intitule_journee' => ['required', 'string'],
            'date' => ['required', 'date'],
            'recurrent' => ['required', 'boolean'],
            'actif' => ['boolean'],
            'est_national' => ['boolean'],
        ];
    }
}