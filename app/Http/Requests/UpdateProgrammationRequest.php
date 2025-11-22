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
class UpdateProgrammationRequest extends FormRequest
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
        $programmation = $this->route('programmation');

        if (!$sirene || !$programmation) {
            return false;
        }

        // Vérifier que la programmation appartient bien à la sirène
        if ($programmation->sirene_id !== $sirene->id) {
            return false;
        }

        // Vérifier que la sirène a un abonnement actif
        $ecole = $sirene->ecole;
        if (!$ecole || !$ecole->hasActiveSubscription()) {
            return false;
        }

        // Les admins peuvent modifier des programmations pour n'importe quelle sirène
        if ($user->isAdmin()) {
            return true;
        }

        // Les écoles peuvent modifier des programmations uniquement pour leurs propres sirènes
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

        // 1. Valider et normaliser les horaires_sonneries (si présents)
        $horaires = $this->input('horaires_sonneries');

        if ($horaires !== null && is_array($horaires)) {
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

        // 2. Valider et normaliser les jours_feries_exceptions (si présents)
        $exceptions = $this->input('jours_feries_exceptions');

        if ($exceptions !== null && is_array($exceptions)) {
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
            // Format: [{"heure": 8, "minute": 0, "jours": [1,2,3,4,5], "duree_sonnerie": 3, "description": "Début cours"}, ...]
            // Note: La validation stricte est effectuée par le HoraireSonnerieDTO dans prepareForValidation()
            'horaires_sonneries' => ['sometimes', 'required', 'array', 'min:1'],
            'horaires_sonneries.*.heure' => ['required_with:horaires_sonneries', 'integer', 'min:0', 'max:23'],
            'horaires_sonneries.*.minute' => ['required_with:horaires_sonneries', 'integer', 'min:0', 'max:59'],
            'horaires_sonneries.*.duree_sonnerie' => ['nullable', 'integer', 'min:1', 'max:30'],
            'horaires_sonneries.*.description' => ['nullable', 'string', 'max:255'],
            'horaires_sonneries.*.jours' => ['required_with:horaires_sonneries', 'array', 'min:1'],
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
