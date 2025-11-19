<?php

namespace App\Http\Requests;

use App\Models\Ecole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="UpdateProgrammationRequest",
 *     title="Update Programmation Request",
 *     description="Request body for updating an existing programmation",
 *     @OA\Property(property="nom_programmation", type="string", maxLength=255, description="Nom de la programmation"),
 *     @OA\Property(property="date_debut", type="string", format="date", description="Date de début de la la programmation"),
 *     @OA\Property(property="date_fin", type="string", format="date", description="Date de fin de la programmation"),
 *     @OA\Property(property="actif", type="boolean", description="Indique si la programmation est active"),
 *     @OA\Property(property="calendrier_id", type="string", format="ulid", nullable=true, description="ID du calendrier scolaire associé"),
 *     @OA\Property(
 *         property="horaires_sonneries",
 *         type="array",
 *         description="Horaires des sonneries au format ESP8266",
 *         @OA\Items(
 *             type="object",
 *             required={"heure", "minute", "jours"},
 *             @OA\Property(property="heure", type="integer", minimum=0, maximum=23, description="Heure (0-23)"),
 *             @OA\Property(property="minute", type="integer", minimum=0, maximum=59, description="Minute (0-59)"),
 *             @OA\Property(
 *                 property="jours",
 *                 type="array",
 *                 description="Jours de la semaine (0=Dimanche, 1=Lundi...6=Samedi)",
 *                 @OA\Items(type="integer", minimum=0, maximum=6)
 *             )
 *         )
 *     ),
 *     @OA\Property(property="jours_feries_inclus", type="boolean", description="Indique si les jours fériés sont inclus"),
 *     @OA\Property(property="jours_feries_exceptions", type="array", @OA\Items(type="object",
 *         @OA\Property(property="date", type="string", format="date"),
 *         @OA\Property(property="action", type="string", enum={"include", "exclude"})
 *     ), nullable=true, description="Exceptions pour les jours fériés"),
 *     @OA\Property(property="abonnement_id", type="string", format="ulid", nullable=true, description="ID de l'abonnement associé"),
 * )
 */
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

            // Horaires de sonnerie au format ESP8266 (CRITIQUES)
            // Format: [{"heure": 8, "minute": 0, "jours": [1,2,3,4,5]}, ...]
            'horaires_sonneries' => [
                'sometimes',
                'required',
                'array',
                'min:1',
                function ($attribute, $value, $fail) {
                    // Vérifier qu'il n'y a pas de doublons
                    $signatures = [];
                    foreach ($value as $horaire) {
                        if (!isset($horaire['heure']) || !isset($horaire['minute']) || !isset($horaire['jours'])) {
                            continue;
                        }
                        $signature = sprintf('%02d:%02d:%s',
                            $horaire['heure'],
                            $horaire['minute'],
                            implode(',', $horaire['jours'])
                        );
                        if (in_array($signature, $signatures)) {
                            $fail('Les horaires de sonnerie ne doivent pas contenir de doublons.');
                            return;
                        }
                        $signatures[] = $signature;
                    }

                    // Vérifier que les horaires sont triés par heure puis minute
                    $sorted = $value;
                    usort($sorted, function ($a, $b) {
                        $timeA = ($a['heure'] ?? 0) * 60 + ($a['minute'] ?? 0);
                        $timeB = ($b['heure'] ?? 0) * 60 + ($b['minute'] ?? 0);
                        return $timeA - $timeB;
                    });

                    // Comparer en JSON car les tableaux peuvent avoir des ordres différents pour 'jours'
                    $valueJson = json_encode(array_map(function($h) {
                        return ['heure' => $h['heure'] ?? 0, 'minute' => $h['minute'] ?? 0];
                    }, $value));
                    $sortedJson = json_encode(array_map(function($h) {
                        return ['heure' => $h['heure'] ?? 0, 'minute' => $h['minute'] ?? 0];
                    }, $sorted));

                    if ($valueJson !== $sortedJson) {
                        $fail('Les horaires de sonnerie doivent être triés dans l\'ordre chronologique.');
                    }
                },
            ],
            'horaires_sonneries.*.heure' => ['required_with:horaires_sonneries', 'integer', 'min:0', 'max:23'],
            'horaires_sonneries.*.minute' => ['required_with:horaires_sonneries', 'integer', 'min:0', 'max:59'],
            'horaires_sonneries.*.jours' => [
                'required_with:horaires_sonneries',
                'array',
                'min:1',
                function ($attribute, $value, $fail) {
                    // Vérifier qu'il n'y a pas de doublons dans les jours
                    if (count($value) !== count(array_unique($value))) {
                        $fail('Les jours ne doivent pas contenir de doublons.');
                    }
                },
            ],
            'horaires_sonneries.*.jours.*' => ['required_with:horaires_sonneries.*.jours', 'integer', 'min:0', 'max:6'],

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

            // Horaires de sonnerie (format ESP8266)
            'horaires_sonneries.required' => 'Les horaires de sonnerie sont obligatoires.',
            'horaires_sonneries.array' => 'Les horaires de sonnerie doivent être un tableau.',
            'horaires_sonneries.min' => 'Au moins un horaire de sonnerie est requis.',
            'horaires_sonneries.*.heure.required_with' => 'L\'heure est obligatoire pour chaque horaire.',
            'horaires_sonneries.*.heure.integer' => 'L\'heure doit être un nombre entier.',
            'horaires_sonneries.*.heure.min' => 'L\'heure doit être comprise entre 0 et 23.',
            'horaires_sonneries.*.heure.max' => 'L\'heure doit être comprise entre 0 et 23.',
            'horaires_sonneries.*.minute.required_with' => 'La minute est obligatoire pour chaque horaire.',
            'horaires_sonneries.*.minute.integer' => 'La minute doit être un nombre entier.',
            'horaires_sonneries.*.minute.min' => 'La minute doit être comprise entre 0 et 59.',
            'horaires_sonneries.*.minute.max' => 'La minute doit être comprise entre 0 et 59.',
            'horaires_sonneries.*.jours.required_with' => 'Les jours sont obligatoires pour chaque horaire.',
            'horaires_sonneries.*.jours.array' => 'Les jours doivent être un tableau.',
            'horaires_sonneries.*.jours.min' => 'Au moins un jour est requis pour chaque horaire.',
            'horaires_sonneries.*.jours.*.required_with' => 'Chaque jour est obligatoire.',
            'horaires_sonneries.*.jours.*.integer' => 'Chaque jour doit être un nombre entier.',
            'horaires_sonneries.*.jours.*.min' => 'Chaque jour doit être compris entre 0 (Dimanche) et 6 (Samedi).',
            'horaires_sonneries.*.jours.*.max' => 'Chaque jour doit être compris entre 0 (Dimanche) et 6 (Samedi).',

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
