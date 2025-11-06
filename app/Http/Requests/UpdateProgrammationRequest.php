<?php

namespace App\Http\Requests;

use App\Models\Ecole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProgrammationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        // Seules les écoles peuvent modifier des programmations
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
            // Relations (en lecture seule, ne peuvent pas être modifiées)
            'ecole_id' => ['sometimes', 'prohibited'],
            'site_id' => ['sometimes', 'prohibited'],
            'sirene_id' => ['sometimes', 'prohibited'],
            'cree_par' => ['sometimes', 'prohibited'],

            // Informations de base
            'nom_programmation' => ['sometimes', 'required', 'string', 'max:255'],
            'date_debut' => ['sometimes', 'required', 'date', 'before_or_equal:date_fin'],
            'date_fin' => ['sometimes', 'required', 'date', 'after_or_equal:date_debut'],
            'actif' => ['sometimes', 'boolean'],

            // Calendrier scolaire (optionnel)
            'calendrier_id' => ['sometimes', 'nullable', 'exists:calendriers_scolaires,id'],

            // Horaires de sonnerie (CRITIQUES)
            'horaires_sonneries' => [
                'sometimes',
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
            'horaires_sonneries.*' => ['required_with:horaires_sonneries', 'date_format:H:i'],

            // Jours de la semaine (CRITIQUES)
            'jour_semaine' => [
                'sometimes',
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
                'required_with:jour_semaine',
                'string',
                Rule::in(['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche']),
            ],

            // Gestion des jours fériés
            'jours_feries_inclus' => ['sometimes', 'boolean'],
            'jours_feries_exceptions' => ['sometimes', 'nullable', 'array'],
            'jours_feries_exceptions.*.date' => ['required', 'date_format:Y-m-d'],
            'jours_feries_exceptions.*.action' => ['required', 'string', Rule::in(['include', 'exclude'])],

            // Champs générés (en lecture seule, générés automatiquement)
            'chaine_programmee' => ['sometimes', 'prohibited'],
            'chaine_cryptee' => ['sometimes', 'prohibited'],

            // Abonnement (optionnel, ne peut être modifié que par admin)
            'abonnement_id' => ['sometimes', 'nullable', 'exists:abonnements,id'],
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            // Champs interdits
            'ecole_id.prohibited' => 'L\'école ne peut pas être modifiée.',
            'site_id.prohibited' => 'Le site ne peut pas être modifié.',
            'sirene_id.prohibited' => 'La sirène ne peut pas être modifiée.',
            'cree_par.prohibited' => 'Le créateur ne peut pas être modifié.',
            'chaine_programmee.prohibited' => 'La chaîne programmée est générée automatiquement.',
            'chaine_cryptee.prohibited' => 'La chaîne cryptée est générée automatiquement.',

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
            'horaires_sonneries.*.required_with' => 'Chaque horaire de sonnerie est obligatoire.',
            'horaires_sonneries.*.date_format' => 'Chaque horaire de sonnerie doit être au format HH:MM (exemple: 08:00).',

            // Jours de la semaine
            'jour_semaine.required' => 'Les jours de la semaine sont obligatoires.',
            'jour_semaine.array' => 'Les jours de la semaine doivent être un tableau.',
            'jour_semaine.min' => 'Au moins un jour de la semaine est requis.',
            'jour_semaine.*.required_with' => 'Chaque jour de la semaine est obligatoire.',
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
