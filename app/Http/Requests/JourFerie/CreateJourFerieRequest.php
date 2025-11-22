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
        $user = $this->user();

        if (!$user) {
            return false;
        }

        $ecoleId = $this->input('ecole_id') ?? $this->route('ecole');

        // Si l'utilisateur est une école, ecole_id doit être obligatoirement fourni
        if ($user->user_account_type_type === Ecole::class) {
            if (!$ecoleId) {
                return false; // Les écoles ne peuvent pas créer de jours fériés nationaux
            }

            $ecole = Ecole::find($ecoleId);

            if (!$ecole) {
                return false;
            }

            // Vérifier que l'école a un abonnement actif
            if (!$ecole->hasActiveSubscription()) {
                return false;
            }

            // Les écoles peuvent créer des jours fériés uniquement pour elles-mêmes
            return $ecole->id === $user->user_account_type_id;
        }

        // Pour les admins
        if ($user->isAdmin()) {
            return true; // Les admins peuvent tout faire sans restriction d'abonnement
        }

        return false;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $data = [];

        // Auto-assigner ecole_id si l'utilisateur est une école et n'a pas fourni ecole_id
        if ($this->user() && $this->user()->user_account_type_type === Ecole::class) {
            if (!$this->has('ecole_id') && !$this->route('ecole')) {
                $data['ecole_id'] = $this->user()->user_account_type_id;
            }
        }

        if ($this->has('date')) {
            $data['date'] = $this->parseDate($this->date);
        }

        if (!empty($data)) {
            $this->merge($data);
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
            'pays_id' => ['nullable', 'string', 'exists:pays,id'],
            'ecole_id' => ['nullable', 'string', 'exists:ecoles,id'],
            'intitule_journee' => ['required', 'string', 'max:100'],
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

            // 1. Si est_national = true et calendrier_id est null, pays_id est obligatoire
            if ($this->est_national && !$this->calendrier_id && !$this->pays_id) {
                $validator->errors()->add(
                    'pays_id',
                    "Le pays est obligatoire pour un jour férié national sans calendrier."
                );
                return;
            }

            // 2. Vérifier que si est_national = true, ecole_id doit être null
            if ($this->est_national && $this->ecole_id) {
                $validator->errors()->add(
                    'ecole_id',
                    "Un jour férié national ne peut pas être spécifique à une école."
                );
                return;
            }

            // Validations liées au calendrier (si calendrier_id est fourni)
            if ($this->calendrier_id) {
                $calendrier = CalendrierScolaire::find($this->calendrier_id);
                if (!$calendrier) {
                    return;
                }

                // 3. Vérifier que l'école appartient au pays du calendrier
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

                // 4. Vérifier que la date est dans la période de l'année scolaire
                if ($this->date && $calendrier->date_rentree && $calendrier->date_fin_annee) {
                    $date = Carbon::parse($this->date);
                    if ($date->lt($calendrier->date_rentree) || $date->gt($calendrier->date_fin_annee)) {
                        $validator->errors()->add(
                            'date',
                            "La date doit être comprise entre {$calendrier->date_rentree->format('d/m/Y')} et {$calendrier->date_fin_annee->format('d/m/Y')}."
                        );
                        return;
                    }
                }
            }

            // 5. Vérifier l'unicité (même date + calendrier + ecole)
            $query = JourFerie::where('date', $this->date);
            if ($this->calendrier_id) {
                $query->where('calendrier_id', $this->calendrier_id);
            }
            if ($this->ecole_id) {
                $query->where('ecole_id', $this->ecole_id);
            } else {
                $query->whereNull('ecole_id');
            }

            if ($query->exists()) {
                $validator->errors()->add(
                    'date',
                    "Un jour férié existe déjà pour cette date."
                );
            }
        });
    }
}