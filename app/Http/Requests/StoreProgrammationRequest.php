<?php

namespace App\Http\Requests;

use App\Models\Ecole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProgrammationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        // Seules les écoles peuvent créer des programmations
        if (!$user || $user->user_account_type_type !== Ecole::class) {
            return false;
        }

        // Vérifier que la sirène appartient à l'école connectée
        $sirene = $this->route('sirene');
        if (!$sirene) {
            return false;
        }

        return $sirene->ecole_id === $user->user_account_type_id;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Informations de base
            'nom_programmation' => ['required', 'string', 'max:255'],
            'date_debut' => ['required', 'date', 'before_or_equal:date_fin'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
            'actif' => ['sometimes', 'boolean'],

            // Calendrier scolaire (optionnel)
            'calendrier_id' => ['nullable', 'exists:calendriers_scolaires,id'],

            // Horaires de sonnerie (CRITIQUES - requis)
            'horaires_sonneries' => [
                'required',
                'array',
                'min:1',
                function ($attribute, $value, $fail) {
                    // Vérifier qu'il n'y a pas de doublons
                    if (count($value) !== count(array_unique($value))) {
                        $fail('Les horaires de sonnerie ne doivent pas contenir de doublons.');
                    }

                    // Vérifier que les horaires sont triés
                    $sorted = $value;
                    sort($sorted);
                    if ($value !== $sorted) {
                        $fail('Les horaires de sonnerie doivent être triés dans l\'ordre chronologique.');
                    }
                },
            ],
            'horaires_sonneries.*' => ['required', 'date_format:H:i'],

            // Jours de la semaine (CRITIQUES - requis)
            'jour_semaine' => [
                'required',
                'array',
                'min:1',
                function ($attribute, $value, $fail) {
                    // Vérifier qu'il n'y a pas de doublons
                    if (count($value) !== count(array_unique($value))) {
                        $fail('Les jours de la semaine ne doivent pas contenir de doublons.');
                    }
                },
            ],
            'jour_semaine.*' => [
                'required',
                'string',
                Rule::in(['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche']),
            ],

            // Gestion des jours fériés
            'jours_feries_inclus' => ['boolean'],
            'jours_feries_exceptions' => ['nullable', 'array'],
            'jours_feries_exceptions.*.date' => ['required', 'date_format:Y-m-d'],
            'jours_feries_exceptions.*.action' => ['required', 'string', Rule::in(['include', 'exclude'])],

            // Validation de l'abonnement actif
            'abonnement_id' => [
                'sometimes',
                'nullable',
                'exists:abonnements,id',
                function ($attribute, $value, $fail) {
                    // Récupérer l'école de la sirène
                    $sirene = $this->route('sirene');
                    if (!$sirene) {
                        $fail('Sirène invalide.');
                        return;
                    }

                    $ecole = $sirene->ecole;
                    if (!$ecole) {
                        $fail('École introuvable pour cette sirène.');
                        return;
                    }

                    // Vérifier qu'un abonnement actif existe
                    if (!$ecole->hasActiveSubscription()) {
                        $fail('Vous devez avoir un abonnement actif pour créer une programmation.');
                        return;
                    }

                    // Récupérer l'abonnement actif
                    $abonnementActif = $ecole->abonnementActif;

                    // Vérifier que les dates de programmation sont couvertes par l'abonnement
                    $dateDebut = $this->input('date_debut');
                    $dateFin = $this->input('date_fin');

                    if ($dateDebut && $dateFin && $abonnementActif) {
                        $abonnementDateDebut = $abonnementActif->date_debut->format('Y-m-d');
                        $abonnementDateFin = $abonnementActif->date_fin->format('Y-m-d');

                        if ($dateDebut < $abonnementDateDebut || $dateFin > $abonnementDateFin) {
                            $fail(sprintf(
                                'Les dates de programmation (%s au %s) doivent être couvertes par votre abonnement actif (%s au %s).',
                                $dateDebut,
                                $dateFin,
                                $abonnementDateDebut,
                                $abonnementDateFin
                            ));
                        }
                    }
                },
            ],
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            // Nom programmation
            'nom_programmation.required' => 'Le nom de la programmation est obligatoire.',
            'nom_programmation.string' => 'Le nom de la programmation doit être une chaîne de caractères.',
            'nom_programmation.max' => 'Le nom de la programmation ne peut pas dépasser 255 caractères.',

            // Dates
            'date_debut.required' => 'La date de début est obligatoire.',
            'date_debut.date' => 'La date de début doit être une date valide.',
            'date_debut.before_or_equal' => 'La date de début doit être antérieure ou égale à la date de fin.',
            'date_fin.required' => 'La date de fin est obligatoire.',
            'date_fin.date' => 'La date de fin doit être une date valide.',
            'date_fin.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',

            // Horaires de sonnerie
            'horaires_sonneries.required' => 'Les horaires de sonnerie sont obligatoires.',
            'horaires_sonneries.array' => 'Les horaires de sonnerie doivent être un tableau.',
            'horaires_sonneries.min' => 'Au moins un horaire de sonnerie est requis.',
            'horaires_sonneries.*.required' => 'Chaque horaire de sonnerie est obligatoire.',
            'horaires_sonneries.*.date_format' => 'Chaque horaire de sonnerie doit être au format HH:MM (exemple: 08:00).',

            // Jours de la semaine
            'jour_semaine.required' => 'Les jours de la semaine sont obligatoires.',
            'jour_semaine.array' => 'Les jours de la semaine doivent être un tableau.',
            'jour_semaine.min' => 'Au moins un jour de la semaine est requis.',
            'jour_semaine.*.required' => 'Chaque jour de la semaine est obligatoire.',
            'jour_semaine.*.in' => 'Le jour de la semaine doit être l\'un des suivants: Lundi, Mardi, Mercredi, Jeudi, Vendredi, Samedi, Dimanche.',

            // Jours fériés
            'jours_feries_inclus.boolean' => 'Le champ jours fériés inclus doit être vrai ou faux.',
            'jours_feries_exceptions.array' => 'Les exceptions de jours fériés doivent être un tableau.',
            'jours_feries_exceptions.*.date.required' => 'La date est requise pour chaque exception de jour férié.',
            'jours_feries_exceptions.*.date.date_format' => 'La date doit être au format YYYY-MM-DD (exemple: 2025-12-25).',
            'jours_feries_exceptions.*.action.required' => 'L\'action est requise pour chaque exception de jour férié.',
            'jours_feries_exceptions.*.action.in' => 'L\'action doit être "include" ou "exclude".',

            // Autres
            'calendrier_id.exists' => 'Le calendrier scolaire sélectionné n\'existe pas.',
            'actif.boolean' => 'Le statut actif doit être vrai ou faux.',
            'abonnement_id.exists' => 'L\'abonnement sélectionné n\'existe pas.',
        ];
    }
}
