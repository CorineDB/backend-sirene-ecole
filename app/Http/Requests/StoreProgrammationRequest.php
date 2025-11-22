<?php

namespace App\Http\Requests;

use App\DTO\HoraireSonnerieDTO;
use App\DTO\JourFerieExceptionDTO;
use App\Models\Ecole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
 *             @OA\Property(property="duree_sonnerie", type="integer", minimum=1, maximum=30, nullable=true, description="Durée de la sonnerie en secondes (défaut: 3s)"),
 *             @OA\Property(property="description", type="string", maxLength=255, nullable=true, description="Description de l'horaire (ex: 'Début des cours', 'Récréation')"),
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

        if (!$user) {
            return false;
        }

        $sirene = $this->route('sirene');
        if (!$sirene) {
            return false;
        }

        // Vérifier que la sirène a un abonnement actif
        $ecole = $sirene->ecole;
        if (!$ecole || !$ecole->hasActiveSubscription()) {
            return false;
        }

        // Les admins peuvent créer des programmations pour n'importe quelle sirène
        if ($user->isAdmin()) {
            return true;
        }

        // Les écoles peuvent créer des programmations uniquement pour leurs propres sirènes
        if ($user->user_account_type_type === Ecole::class) {
            return $sirene->ecole_id === $user->user_account_type_id;
        }

        return false;
    }

    /**
     * Prepare the data for validation using DTOs
     */
    protected function prepareForValidation(): void
    {
        $mergeData = [];

        // 1. Valider et normaliser les horaires_sonneries
        $horaires = $this->input('horaires_sonneries', []);

        if (!empty($horaires) && is_array($horaires)) {
            $normalizedHoraires = [];
            $signaturesHoraires = [];

            foreach ($horaires as $index => $horaireData) {
                try {
                    // Valider et normaliser avec le DTO
                    $dto = new HoraireSonnerieDTO($horaireData);

                    // Vérifier les doublons avec la signature du DTO
                    $signature = $dto->getSignature();
                    if (in_array($signature, $signaturesHoraires)) {
                        throw ValidationException::withMessages([
                            "horaires_sonneries.{$index}" => "Horaire en double détecté: {$dto->getFormattedTime()} pour les jours " . implode(', ', $dto->getJoursNoms())
                        ]);
                    }
                    $signaturesHoraires[] = $signature;

                    // Normaliser les données avec le DTO
                    $normalizedHoraires[] = $dto->toArray();

                } catch (\InvalidArgumentException $e) {
                    // Convertir l'exception du DTO en ValidationException Laravel
                    throw ValidationException::withMessages([
                        "horaires_sonneries.{$index}" => $e->getMessage()
                    ]);
                }
            }

            // Vérifier que les horaires sont triés chronologiquement
            $sorted = $normalizedHoraires;
            usort($sorted, function ($a, $b) {
                $timeA = $a['heure'] * 60 + $a['minute'];
                $timeB = $b['heure'] * 60 + $b['minute'];
                return $timeA - $timeB;
            });

            $isSorted = true;
            foreach ($normalizedHoraires as $idx => $horaire) {
                if ($horaire['heure'] !== $sorted[$idx]['heure'] ||
                    $horaire['minute'] !== $sorted[$idx]['minute']) {
                    $isSorted = false;
                    break;
                }
            }

            if (!$isSorted) {
                throw ValidationException::withMessages([
                    'horaires_sonneries' => 'Les horaires de sonnerie doivent être triés dans l\'ordre chronologique.'
                ]);
            }

            $mergeData['horaires_sonneries'] = $normalizedHoraires;
        }

        // 2. Valider et normaliser les jours_feries_exceptions
        $exceptions = $this->input('jours_feries_exceptions');

        if (!empty($exceptions) && is_array($exceptions)) {
            $normalizedExceptions = [];
            $signaturesExceptions = [];
            $datesVues = [];

            foreach ($exceptions as $index => $exceptionData) {
                try {
                    // Valider et normaliser avec le DTO
                    $dto = new JourFerieExceptionDTO($exceptionData);

                    // Vérifier les doublons de dates (peu importe l'action)
                    $date = $dto->getDate();
                    if (in_array($date, $datesVues)) {
                        throw ValidationException::withMessages([
                            "jours_feries_exceptions.{$index}" => "Exception en double pour la date {$dto->getFormattedDate('d/m/Y')}. Une seule exception par date est autorisée."
                        ]);
                    }
                    $datesVues[] = $date;

                    // Vérifier les doublons complets (date + action)
                    $signature = $dto->getSignature();
                    if (in_array($signature, $signaturesExceptions)) {
                        throw ValidationException::withMessages([
                            "jours_feries_exceptions.{$index}" => "Exception en double détecté: {$dto->getDescription()}"
                        ]);
                    }
                    $signaturesExceptions[] = $signature;

                    // Normaliser les données avec le DTO
                    $normalizedExceptions[] = $dto->toArray();

                } catch (\InvalidArgumentException $e) {
                    // Convertir l'exception du DTO en ValidationException Laravel
                    throw ValidationException::withMessages([
                        "jours_feries_exceptions.{$index}" => $e->getMessage()
                    ]);
                }
            }

            // Trier les exceptions par date chronologique
            usort($normalizedExceptions, function ($a, $b) {
                return strcmp($a['date'], $b['date']);
            });

            $mergeData['jours_feries_exceptions'] = $normalizedExceptions;
        }

        // Fusionner toutes les données normalisées
        if (!empty($mergeData)) {
            $this->merge($mergeData);
        }
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
            // Format: [{"heure": 8, "minute": 0, "jours": [1,2,3,4,5], "duree_sonnerie": 3, "description": "Début cours"}, ...]
            // Note: La validation stricte est effectuée par le HoraireSonnerieDTO dans prepareForValidation()
            'horaires_sonneries' => ['required', 'array', 'min:1'],
            'horaires_sonneries.*.heure' => ['required', 'integer', 'min:0', 'max:23'],
            'horaires_sonneries.*.minute' => ['required', 'integer', 'min:0', 'max:59'],
            'horaires_sonneries.*.duree_sonnerie' => ['nullable', 'integer', 'min:1', 'max:30'],
            'horaires_sonneries.*.description' => ['nullable', 'string', 'max:255'],
            'horaires_sonneries.*.jours' => ['required', 'array', 'min:1'],
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
            'horaires_sonneries.*.duree_sonnerie.integer' => 'La durée de sonnerie doit être un nombre entier.',
            'horaires_sonneries.*.duree_sonnerie.min' => 'La durée de sonnerie doit être d\'au moins 1 seconde.',
            'horaires_sonneries.*.duree_sonnerie.max' => 'La durée de sonnerie ne peut pas dépasser 30 secondes.',
            'horaires_sonneries.*.description.string' => 'La description doit être une chaîne de caractères.',
            'horaires_sonneries.*.description.max' => 'La description ne peut pas dépasser 255 caractères.',
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
