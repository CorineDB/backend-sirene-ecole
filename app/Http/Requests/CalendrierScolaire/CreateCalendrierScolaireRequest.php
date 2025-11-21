<?php

namespace App\Http\Requests\CalendrierScolaire;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="CreateCalendrierScolaireRequest",
 *     title="Create School Calendar Request",
 *     description="Request body for creating a new school calendar entry",
 *     required={"pays_id", "annee_scolaire", "date_rentree", "date_fin_annee"},
 *     @OA\Property(
 *         property="pays_id",
 *         type="string",
 *         format="uuid",
 *         description="ID of the country this calendar belongs to"
 *     ),
 *     @OA\Property(
 *         property="annee_scolaire",
 *         type="string",
 *         description="Academic year (e.g., '2023-2024')"
 *     ),
 *     @OA\Property(
 *         property="description",
 *         type="string",
 *         nullable=true,
 *         description="Description of the school calendar"
 *     ),
 *     @OA\Property(
 *         property="date_rentree",
 *         type="string",
 *         format="date",
 *         description="Start date of the academic year"
 *     ),
 *     @OA\Property(
 *         property="date_fin_annee",
 *         type="string",
 *         format="date",
 *         description="End date of the academic year"
 *     ),
 *     @OA\Property(
 *         property="periodes_vacances",
 *         type="array",
 *         nullable=true,
 *         description="Array of vacation periods",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="nom", type="string", example="Vacances de Noël"),
 *             @OA\Property(property="date_debut", type="string", format="date", example="2023-12-20"),
 *             @OA\Property(property="date_fin", type="string", format="date", example="2024-01-05")
 *         )
 *     ),
 *     @OA\Property(
 *         property="jours_feries_defaut",
 *         type="array",
 *         nullable=true,
 *         description="Array of default public holidays",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="nom", type="string", example="Jour de l'An"),
 *             @OA\Property(property="date", type="string", format="date", example="2024-01-01")
 *         )
 *     ),
 *     @OA\Property(
 *         property="actif",
 *         type="boolean",
 *         description="Is this calendar active?",
 *         default=true
 *     )
 * )
 */
class CreateCalendrierScolaireRequest extends FormRequest
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
    protected function prepareForValidation(): void
    {
        $this->merge([
            'date_rentree' => $this->parseDate($this->date_rentree),
            'date_fin_annee' => $this->parseDate($this->date_fin_annee),
        ]);

        if ($this->has('periodes_vacances')) {
            $periodesVacances = collect($this->periodes_vacances)->map(function ($periode) {
                $periode['date_debut'] = $this->parseDate($periode['date_debut']);
                $periode['date_fin'] = $this->parseDate($periode['date_fin']);
                return $periode;
            })->toArray();
            $this->merge(['periodes_vacances' => $periodesVacances]);
        }

        if ($this->has('jours_feries_defaut')) {
            $joursFeriesDefaut = collect($this->jours_feries_defaut)->map(function ($jourFerie) {
                $jourFerie['date'] = $this->parseDate($jourFerie['date']);
                return $jourFerie;
            })->toArray();
            $this->merge(['jours_feries_defaut' => $joursFeriesDefaut]);
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

    public function rules(): array
    {
        return [
            'pays_id' => ['required', 'string', 'exists:pays,id'],
            'annee_scolaire' => [
                'required',
                'regex:/^\d{4}-\d{4}$/', // Ensures format YYYY-YYYY
                function ($attribute, $value, $fail) {
                    $years = explode('-', $value);
                    $startYear = (int) $years[0];
                    $endYear = (int) $years[1];

                    if ($endYear !== $startYear + 1) {
                        $fail('The ' . $attribute . ' must be in consecutive years (e.g., 2025-2026).');
                    }

                    $currentYear = (int) date('Y');
                    $currentMonth = (int) date('m');
                    $academicCurrentYearStart = ($currentMonth >= 9) ? $currentYear : $currentYear - 1;

                    // Allow past academic year, current and next academic year
                    if ($startYear > $academicCurrentYearStart + 1) {
                        $fail('The ' . $attribute . ' cannot be more than one academic year in the future.');
                    }
                },
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'date_rentree' => ['required', 'date_format:Y-m-d'],
            'date_fin_annee' => ['required', 'date_format:Y-m-d', 'after:date_rentree'],
            'periodes_vacances' => ['nullable', 'array'],
            'periodes_vacances.*.nom' => ['required_with:periodes_vacances', 'string', 'max:100'],
            'periodes_vacances.*.date_debut' => ['required_with:periodes_vacances', 'date_format:Y-m-d'],
            'periodes_vacances.*.date_fin' => ['required_with:periodes_vacances', 'date_format:Y-m-d', 'after_or_equal:periodes_vacances.*.date_debut'],
            'jours_feries_defaut' => ['nullable', 'array'],
            'jours_feries_defaut.*.nom' => ['required_with:jours_feries_defaut', 'string', 'max:100'],
            'jours_feries_defaut.*.date' => ['required_with:jours_feries_defaut', 'date_format:Y-m-d']
        ];
    }
}
