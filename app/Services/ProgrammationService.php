<?php

namespace App\Services;

use App\DTO\HoraireSonnerieDTO;
use App\DTO\JourFerieExceptionDTO;
use App\Models\Programmation;
use App\Services\Contracts\ProgrammationServiceInterface;
use App\Repositories\Contracts\ProgrammationRepositoryInterface;
use App\Services\Contracts\JourFerieServiceInterface;
use App\Traits\JsonResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ProgrammationService extends BaseService implements ProgrammationServiceInterface
{
    use JsonResponseTrait;

    /**
     * @var JourFerieServiceInterface
     */
    protected $jourFerieService;

    /**
     * @param ProgrammationRepositoryInterface $repository
     * @param JourFerieServiceInterface $jourFerieService
     */
    public function __construct(ProgrammationRepositoryInterface $repository, JourFerieServiceInterface $jourFerieService)
    {
        parent::__construct($repository);
        $this->jourFerieService = $jourFerieService;
    }

    /**
     * @param string $sireneId
     * @return JsonResponse
     */
    public function getBySireneId(string $sireneId): JsonResponse
    {
        try {
            $programmations = $this->repository->getBySireneId($sireneId);
            return $this->successResponse('Programmations for sirene retrieved successfully.', $programmations);
        } catch (Exception $e) {
            Log::error("Error in " . get_class($this) . "::getBySireneId - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Get paginated programmations for a sirene
     *
     * @param string $sireneId
     * @param int $perPage Number of items per page (default: 15)
     * @return JsonResponse
     */
    public function getPaginatedBySireneId(string $sireneId, int $perPage = 15): JsonResponse
    {
        try {
            $programmations = $this->repository->getPaginatedBySireneId($sireneId, $perPage);

            return response()->json([
                'success' => true,
                'message' => 'Programmations paginées récupérées avec succès.',
                'data' => $programmations->items(),
                'pagination' => [
                    'current_page' => $programmations->currentPage(),
                    'per_page' => $programmations->perPage(),
                    'total' => $programmations->total(),
                    'last_page' => $programmations->lastPage(),
                    'from' => $programmations->firstItem(),
                    'to' => $programmations->lastItem(),
                    'has_more_pages' => $programmations->hasMorePages(),
                ],
                'links' => [
                    'first' => $programmations->url(1),
                    'last' => $programmations->url($programmations->lastPage()),
                    'prev' => $programmations->previousPageUrl(),
                    'next' => $programmations->nextPageUrl(),
                ],
            ]);
        } catch (Exception $e) {
            Log::error("Error in " . get_class($this) . "::getPaginatedBySireneId - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Get effective programmations for a sirene on a specific date, considering holidays.
     *
     * @param string $sireneId
     * @param string $date (format Y-m-d)
     * @return JsonResponse
     */
    public function getEffectiveProgrammationsForSirene(string $sireneId, string $date): JsonResponse
    {
        try {
            $programmations = $this->repository->getBySireneId($sireneId);
            $carbonDate = Carbon::parse($date);
            $dayOfWeek = $carbonDate->dayName; // e.g., 'Monday'

            $isHoliday = $this->jourFerieService->isJourFerie($date);

            $effectiveProgrammations = $programmations->filter(function (Programmation $programmation) use ($isHoliday, $dayOfWeek, $date) {
                // Check if the programming is active for the current day of the week
                if (!in_array($dayOfWeek, $programmation->jour_semaine)) {
                    return false;
                }

                $shouldIncludeHoliday = $programmation->jours_feries_inclus;

                // Check for specific holiday exceptions
                if (is_array($programmation->jours_feries_exceptions)) {
                    foreach ($programmation->jours_feries_exceptions as $exception) {
                        if (isset($exception['date']) && $exception['date'] === $date) {
                            if (isset($exception['action'])) {
                                $shouldIncludeHoliday = ($exception['action'] === 'include');
                            }
                            break; // Found an exception for this date, no need to check further
                        }
                    }
                }

                // If it's a holiday and the final decision is NOT to include it, filter it out
                if ($isHoliday && !$shouldIncludeHoliday) {
                    return false;
                }

                // Further checks could include date_debut, date_fin, vacances, etc.
                // For now, we focus on jours_feries_inclus and jours_feries_exceptions

                return true;
            });

            return $this->successResponse('Effective programmations for sirene retrieved successfully.', $effectiveProgrammations->values());
        } catch (Exception $e) {
            Log::error("Error in " . get_class($this) . "::getEffectiveProgrammationsForSirene - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Override create() pour auto-remplir les champs système et générer les chaînes cryptées
     *
     * @param array $data
     * @return JsonResponse
     */
    public function create(array $data): JsonResponse
    {
        try {
            DB::beginTransaction();

            // 1. Récupérer la sirène et ses relations
            $sirene = \App\Models\Sirene::find($data['sirene_id']);
            if (!$sirene) {
                DB::rollBack();
                return $this->errorResponse('Sirène introuvable.', 404);
            }

            // 2. Récupérer l'école
            $ecole = $sirene->ecole;
            if (!$ecole) {
                DB::rollBack();
                return $this->errorResponse('École introuvable pour cette sirène.', 404);
            }

            // 3. Vérifier l'abonnement actif
            $abonnementActif = $ecole->abonnementActif;
            if (!$abonnementActif) {
                DB::rollBack();
                return $this->errorResponse('Aucun abonnement actif trouvé pour cette école.', 403);
            }

            // 4. Auto-remplir les champs système
            $data['ecole_id'] = $ecole->id;
            $data['site_id'] = $sirene->site_id;
            $data['abonnement_id'] = $abonnementActif->id;
            $data['cree_par'] = auth()->id();
            $data['actif'] = $data['actif'] ?? true;

            // 5. Créer la programmation
            $programmation = $this->repository->create($data);

            // 6. Générer les chaînes cryptées
            $programmation->sauvegarderChainesCryptees();

            // 7. Recharger avec les relations
            $programmation->load(['ecole', 'site', 'sirene', 'abonnement', 'calendrier', 'creePar']);

            DB::commit();

            // 8. Logging enrichi avec les DTOs
            $horairesFormatted = collect($programmation->horaires_sonneries ?? [])->map(function ($horaire) {
                try {
                    $dto = new HoraireSonnerieDTO($horaire);
                    return [
                        'time' => $dto->getFormattedTime(),
                        'jours' => $dto->getJoursNoms(),
                        'duree' => $dto->getDureeSonnerie() . 's',
                        'description' => $dto->getDescription(),
                        'signature' => $dto->getSignature(),
                    ];
                } catch (\Exception $e) {
                    return $horaire; // Fallback si DTO échoue
                }
            })->toArray();

            $exceptionsFormatted = collect($programmation->jours_feries_exceptions ?? [])->map(function ($exception) {
                try {
                    $dto = new JourFerieExceptionDTO($exception);
                    return [
                        'date' => $dto->getFormattedDate('d/m/Y'),
                        'date_iso' => $dto->getDate(),
                        'action' => $dto->getActionLabel(),
                        'est_national' => $dto->isNational() ? 'national' : ($dto->isLocal() ? 'local' : 'non spécifié'),
                        'recurrent' => $dto->isRecurrent() ? 'récurrent' : ($dto->isExceptionnel() ? 'exceptionnel' : 'non spécifié'),
                        'description' => $dto->getDescription(),
                    ];
                } catch (\Exception $e) {
                    return $exception; // Fallback si DTO échoue
                }
            })->toArray();

            Log::info("Programmation créée avec succès", [
                'programmation_id' => $programmation->id,
                'nom' => $programmation->nom_programmation,
                'sirene_id' => $programmation->sirene_id,
                'ecole_id' => $programmation->ecole_id,
                'horaires_formattes' => $horairesFormatted,
                'nb_horaires' => count($horairesFormatted),
                'exceptions_jours_feries' => $exceptionsFormatted,
                'nb_exceptions' => count($exceptionsFormatted),
            ]);

            return $this->createdResponse($programmation, 'Programmation créée avec succès.');

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in " . get_class($this) . "::create - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Override update() pour régénérer les chaînes cryptées si les horaires ou jours changent
     *
     * @param string $id
     * @param array $data
     * @return JsonResponse
     */
    public function update(string $id, array $data): JsonResponse
    {
        try {
            DB::beginTransaction();

            // 1. Récupérer la programmation existante
            $programmation = $this->repository->find($id);
            if (!$programmation) {
                DB::rollBack();
                return $this->notFoundResponse('Programmation non trouvée.');
            }

            // 2. Vérifier si les champs critiques ont changé (nécessitent régénération des chaînes)
            $horairesDirty = isset($data['horaires_sonneries'])
                && json_encode($data['horaires_sonneries']) !== json_encode($programmation->horaires_sonneries);

            $nomDirty = isset($data['nom_programmation'])
                && $data['nom_programmation'] !== $programmation->nom_programmation;

            $datesDirty = (isset($data['date_debut']) && $data['date_debut'] !== $programmation->date_debut->format('Y-m-d'))
                || (isset($data['date_fin']) && $data['date_fin'] !== $programmation->date_fin->format('Y-m-d'));

            $joursFeriesDirty = isset($data['jours_feries_inclus'])
                && $data['jours_feries_inclus'] !== $programmation->jours_feries_inclus;

            $needsRegeneration = $horairesDirty || $nomDirty || $datesDirty || $joursFeriesDirty;

            // 3. Mettre à jour la programmation
            $updated = $this->repository->update($id, $data);
            if (!$updated) {
                DB::rollBack();
                return $this->errorResponse('Échec de la mise à jour de la programmation.', 500);
            }

            // 4. Recharger la programmation
            $programmation->refresh();

            // 5. Régénérer les chaînes cryptées si nécessaire
            if ($needsRegeneration) {
                $programmation->regenererChainesCryptees();

                Log::info("Chaînes cryptées régénérées pour la programmation", [
                    'programmation_id' => $programmation->id,
                    'raison' => [
                        'horaires_modifies' => $horairesDirty,
                        'nom_modifie' => $nomDirty,
                        'dates_modifiees' => $datesDirty,
                        'jours_feries_modifies' => $joursFeriesDirty,
                    ],
                ]);
            }

            // 6. Recharger avec les relations
            $programmation->load(['ecole', 'site', 'sirene', 'abonnement', 'calendrier', 'creePar']);

            DB::commit();

            // 7. Logging enrichi avec les DTOs
            $horairesFormatted = collect($programmation->horaires_sonneries ?? [])->map(function ($horaire) {
                try {
                    $dto = new HoraireSonnerieDTO($horaire);
                    return [
                        'time' => $dto->getFormattedTime(),
                        'jours' => $dto->getJoursNoms(),
                        'duree' => $dto->getDureeSonnerie() . 's',
                        'description' => $dto->getDescription(),
                    ];
                } catch (\Exception $e) {
                    return $horaire; // Fallback si DTO échoue
                }
            })->toArray();

            $exceptionsFormatted = collect($programmation->jours_feries_exceptions ?? [])->map(function ($exception) {
                try {
                    $dto = new JourFerieExceptionDTO($exception);
                    return [
                        'date' => $dto->getFormattedDate('d/m/Y'),
                        'date_iso' => $dto->getDate(),
                        'action' => $dto->getActionLabel(),
                        'est_national' => $dto->isNational() ? 'national' : ($dto->isLocal() ? 'local' : 'non spécifié'),
                        'recurrent' => $dto->isRecurrent() ? 'récurrent' : ($dto->isExceptionnel() ? 'exceptionnel' : 'non spécifié'),
                        'description' => $dto->getDescription(),
                    ];
                } catch (\Exception $e) {
                    return $exception; // Fallback si DTO échoue
                }
            })->toArray();

            Log::info("Programmation mise à jour avec succès", [
                'programmation_id' => $programmation->id,
                'nom' => $programmation->nom_programmation,
                'chaines_regenerees' => $needsRegeneration,
                'horaires_formattes' => $horairesFormatted,
                'nb_horaires' => count($horairesFormatted),
                'exceptions_jours_feries' => $exceptionsFormatted,
                'nb_exceptions' => count($exceptionsFormatted),
            ]);

            return $this->successResponse('Programmation mise à jour avec succès.', $programmation);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in " . get_class($this) . "::update - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
