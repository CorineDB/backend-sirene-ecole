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
 *     required={"intitule_journee", "date", "calendrier_id"},
 *     @OA\Property(
 *         property="calendrier_id",
 *         type="string",
 *         format="uuid",
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
            'calendrier_id' => ['required', 'string', 'exists:calendriers_scolaires,id'],
            'ecole_id' => ['nullable', 'string', 'exists:ecoles,id'],
<<<<<<< HEAD
            'pays_id' => ['nullable', 'string', 'exists:pays,id'],
            'intitule_journee' => ['required', 'string'],
            'date' => ['required', 'date', 'date_format:Y-m-d'],
            'recurrent' => ['required', 'boolean'],
=======
            'intitule_journee' => ['required', 'string', 'max:100'],
            'date' => ['required', 'date'],
            'recurrent' => ['boolean'],
>>>>>>> 8e98fc25ba6a76e9c10a5865f36a020ac069f227
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

            $calendrier = CalendrierScolaire::find($this->calendrier_id);
            if (!$calendrier) {
                return;
            }

            // 1. Vérifier que l'école appartient au pays du calendrier (via site -> ville -> pays)
            if ($this->ecole_id) {
                $ecole = Ecole::with('sitePrincipal.ville')->find($this->ecole_id);
                if ($ecole && $ecole->sitePrincipal && $ecole->sitePrincipal->ville) {
                    if ($ecole->sitePrincipal->ville->pays_id !== $calendrier->pays_id) {
                        $validator->errors()->add(
                            'ecole_id',
                            "L'école n'appartient pas au pays du calendrier."
                        );
                        return;
                    }
                }
            }

            // 2. Vérifier que la date est dans la période de l'année scolaire
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

            // 3. Vérifier que si est_national = true, ecole_id doit être null
            if ($this->est_national && $this->ecole_id) {
                $validator->errors()->add(
                    'ecole_id',
                    "Un jour férié national ne peut pas être spécifique à une école."
                );
                return;
            }

            // 4. Vérifier l'unicité (même date + calendrier + ecole)
            $exists = JourFerie::where('calendrier_id', $this->calendrier_id)
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