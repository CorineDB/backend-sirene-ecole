<?php

namespace App\Http\Requests;

use App\Models\Ecole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="StoreProgrammationRequest",
 *     title="Store Programmation Request",
 *     description="Request body for storing a new programmation",
 *     required={"nom_programmation", "date_debut", "date_fin", "horaires_sonneries"},
 *     @OA\Property(property="nom_programmation", type="string", maxLength=255, description="Nom de la programmation"),
 *     @OA\Property(property="date_debut", type="string", format="date", description="Date de début de la programmation"),
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
class StoreProgrammationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $sirene = $this->route('sirene');

        // DEBUG
        $debugInfo = [
            'user_exists' => $user !== null,
            'user_id' => $user->id ?? null,
            'user_account_type_type' => $user->user_account_type_type ?? null,
            'user_account_type_id' => $user->user_account_type_id ?? null,
            'expected_type' => Ecole::class,
            'is_ecole' => $user && $user->user_account_type_type === Ecole::class,
            'sirene_exists' => $sirene !== null,
            'sirene_id' => $sirene->id ?? null,
            'sirene_ecole_id' => $sirene->ecole_id ?? null,
            'sirene_belongs_to_user' => $sirene && $user && ($sirene->ecole_id === $user->user_account_type_id),
        ];

        throw new \Exception('DEBUG AUTHORIZE: ' . json_encode($debugInfo, JSON_PRETTY_PRINT));

        // Seules les écoles peuvent créer des programmations
        if (!$user || $user->user_account_type_type !== Ecole::class) {
            return false;
        }

        // Vérifier que la sirène appartient à l'école connectée
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

            // Horaires de sonnerie au format ESP8266 (CRITIQUES - requis)
            // Format: [{"heure": 8, "minute": 0, "jours": [1,2,3,4,5]}, ...]
            'horaires_sonneries' => [
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
            'horaires_sonneries.*.heure' => ['required', 'integer', 'min:0', 'max:23'],
            'horaires_sonneries.*.minute' => ['required', 'integer', 'min:0', 'max:59'],
            'horaires_sonneries.*.jours' => [
                'required',
                'array',
                'min:1',
                function ($attribute, $value, $fail) {
                    // Vérifier qu'il n'y a pas de doublons dans les jours
                    if (count($value) !== count(array_unique($value))) {
                        $fail('Les jours ne doivent pas contenir de doublons.');
                    }
                },
            ],
            'horaires_sonneries.*.jours.*' => ['required', 'integer', 'min:0', 'max:6'],

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

            // Horaires de sonnerie (format ESP8266)
            'horaires_sonneries.required' => 'Les horaires de sonnerie sont obligatoires.',
            'horaires_sonneries.array' => 'Les horaires de sonnerie doivent être un tableau.',
            'horaires_sonneries.min' => 'Au moins un horaire de sonnerie est requis.',
            'horaires_sonneries.*.heure.required' => 'L\'heure est obligatoire pour chaque horaire.',
            'horaires_sonneries.*.heure.integer' => 'L\'heure doit être un nombre entier.',
            'horaires_sonneries.*.heure.min' => 'L\'heure doit être comprise entre 0 et 23.',
            'horaires_sonneries.*.heure.max' => 'L\'heure doit être comprise entre 0 et 23.',
            'horaires_sonneries.*.minute.required' => 'La minute est obligatoire pour chaque horaire.',
            'horaires_sonneries.*.minute.integer' => 'La minute doit être un nombre entier.',
            'horaires_sonneries.*.minute.min' => 'La minute doit être comprise entre 0 et 59.',
            'horaires_sonneries.*.minute.max' => 'La minute doit être comprise entre 0 et 59.',
            'horaires_sonneries.*.jours.required' => 'Les jours sont obligatoires pour chaque horaire.',
            'horaires_sonneries.*.jours.array' => 'Les jours doivent être un tableau.',
            'horaires_sonneries.*.jours.min' => 'Au moins un jour est requis pour chaque horaire.',
            'horaires_sonneries.*.jours.*.required' => 'Chaque jour est obligatoire.',
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
