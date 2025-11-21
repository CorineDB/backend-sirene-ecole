<?php

namespace App\Http\Requests\JourFerie;

use App\Models\CalendrierScolaire;
use App\Models\Ecole;
use App\Models\JourFerie;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="CreateJourFerieRequest",
 *     title="Create Public Holiday Request",
 *     description="Request body for creating a new public holiday entry",
 *     required={"intitule_journee", "date", "pays_id", "annee_scolaire"},
 *     @OA\Property(
 *         property="annee_scolaire",
 *         type="string",
 *         description="Academic year (e.g., '2025-2026') - used to resolve calendrier_id"
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
 *         description="ID of the country this holiday belongs to"
 *     ),
 *     @OA\Property(
 *         property="intitule_journee",
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
 *         property="recurrent",
 *         type="boolean",
 *         description="Is this holiday recurrent every year?",
 *         default=false
 *     ),
 *     @OA\Property(
 *         property="actif",
 *         type="boolean",
 *         description="Is this holiday active?",
 *         default=true
 *     ),
 *     @OA\Property(
 *         property="est_national",
 *         type="boolean",
 *         description="Is this a national holiday?",
 *         default=false
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
        return true;
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
            'annee_scolaire' => ['required', 'string', 'regex:/^\d{4}-\d{4}$/', 'exists:calendriers_scolaires,annee_scolaire'],
            'ecole_id' => ['nullable', 'string', 'exists:ecoles,id'],
            'pays_id' => ['required', 'string', 'exists:pays,id'],
            'intitule_journee' => ['required', 'string'],
            'date' => ['required', 'date'],
            'recurrent' => ['boolean'],
            'actif' => ['boolean'],
            'est_national' => ['boolean'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            // 1. Résoudre le calendrier
            $calendrier = CalendrierScolaire::where('pays_id', $this->pays_id)
                ->where('annee_scolaire', $this->annee_scolaire)
                ->first();

            if (!$calendrier) {
                $validator->errors()->add(
                    'annee_scolaire',
                    "Aucun calendrier scolaire trouvé pour l'année {$this->annee_scolaire} et le pays spécifié."
                );
                return;
            }

            $this->merge(['calendrier_id' => $calendrier->id]);

            // 2. Vérifier que l'école appartient au pays (via site -> ville -> pays)
            if ($this->ecole_id) {
                $ecole = Ecole::with('sitePrincipal.ville')->find($this->ecole_id);
                if ($ecole && $ecole->sitePrincipal && $ecole->sitePrincipal->ville) {
                    if ($ecole->sitePrincipal->ville->pays_id !== $this->pays_id) {
                        $validator->errors()->add(
                            'ecole_id',
                            "L'école n'appartient pas au pays spécifié."
                        );
                        return;
                    }
                }
            }

            // 3. Vérifier que la date est dans la période de l'année scolaire
            if ($this->date) {
                $date = Carbon::parse($this->date);
                if ($date->lt($calendrier->date_rentree) || $date->gt($calendrier->date_fin_annee)) {
                    $validator->errors()->add(
                        'date',
                        "La date doit être comprise entre {$calendrier->date_rentree->format('d/m/Y')} et {$calendrier->date_fin_annee->format('d/m/Y')}."
                    );
                    return;
                }
            }

            // 4. Vérifier l'unicité (même date + calendrier + ecole)
            $exists = JourFerie::where('calendrier_id', $calendrier->id)
                ->where('date', $this->date)
                ->where('ecole_id', $this->ecole_id)
                ->exists();

            if ($exists) {
                $validator->errors()->add(
                    'date',
                    "Un jour férié existe déjà pour cette date."
                );
            }
        });
    }
}