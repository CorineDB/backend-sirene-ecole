<?php

namespace App\Services;

use App\Enums\StatutPanne;
use App\Repositories\Contracts\InterventionRepositoryInterface;
use App\Repositories\Contracts\OrdreMissionRepositoryInterface;
use App\Repositories\Contracts\PanneRepositoryInterface;
use App\Services\Contracts\PanneServiceInterface;
use App\Traits\FiltersByEcole;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PanneService extends BaseService implements PanneServiceInterface
{
    use FiltersByEcole;

    protected OrdreMissionRepositoryInterface $ordreMissionRepository;
    protected InterventionRepositoryInterface $interventionRepository;

    public function __construct(
        PanneRepositoryInterface $repository,
        OrdreMissionRepositoryInterface $ordreMissionRepository,
        InterventionRepositoryInterface $interventionRepository
    ) {
        parent::__construct($repository);
        $this->ordreMissionRepository = $ordreMissionRepository;
        $this->interventionRepository = $interventionRepository;
    }

    /**
     * Surcharge de getAll pour filtrer par école si nécessaire
     */
    public function getAll(int $perPage = 15, array $relations = []): JsonResponse
    {
        try {
            $query = $this->repository->query();

            // Appliquer le filtre école si l'utilisateur est une école
            $query = $this->applyEcoleFilterForPannes($query);

            if (!empty($relations)) {
                $query->with($relations);
            }

            $data = $query->orderBy('created_at', 'desc')->paginate($perPage);
            return $this->successResponse('Données récupérées avec succès.', $data);
        } catch (Exception $e) {
            Log::error("Error in PanneService::getAll - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Surcharge de getById pour vérifier l'accès si école
     */
    public function getById(string $id, array $columns = ['*'], array $relations = []): JsonResponse
    {
        try {
            $query = $this->repository->query()->where('id', $id);

            // Appliquer le filtre école si l'utilisateur est une école
            $query = $this->applyEcoleFilterForPannes($query);

            if (!empty($relations)) {
                $query->with($relations);
            }

            $data = $query->first($columns);

            if (!$data) {
                return $this->errorResponse('Panne non trouvée ou accès non autorisé.', 404);
            }

            return $this->successResponse('Donnée récupérée avec succès.', $data);
        } catch (Exception $e) {
            Log::error("Error in PanneService::getById - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function validerPanne(string $panneId, array $ordreMissionData = []): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Vérifier l'accès à la panne si école
            $query = $this->repository->query()->where('id', $panneId);
            $query = $this->applyEcoleFilterForPannes($query);
            $panneExists = $query->exists();

            if (!$panneExists) {
                return $this->errorResponse('Panne non trouvée ou accès non autorisé.', 404);
            }

            $panne = $this->repository->update($panneId, [
                'statut' => StatutPanne::VALIDEE,
                'valide_par' => auth()->id(),
                'date_validation' => now(),
            ]);

            // Fetch the panne with its site relationship
            $panneWithSite = $this->repository->find($panneId, ['*'], ['site']);

            // Merge default data with provided data
            $ordreMissionPayload = array_merge([
                'panne_id' => $panneWithSite->id,
                'ville_id' => $panneWithSite->site->ville_id ?? null,
                'statut' => 'en_attente',
                'date_generation' => now(),
                'nombre_techniciens_acceptes' => 0,
            ], $ordreMissionData);

            // Create OrdreMission
            $ordreMission = $this->ordreMissionRepository->create($ordreMissionPayload);

            // Créer automatiquement une intervention d'inspection
            $intervention = $this->interventionRepository->create([
                'panne_id' => $panneWithSite->id,
                'ordre_mission_id' => $ordreMission->id,
                'type_intervention' => 'inspection',
                'nombre_techniciens_requis' => 1,
                'statut' => 'planifiee',
                'date_assignation' => now(),
            ]);

            DB::commit();
            return $this->successResponse('Panne validée, ordre de mission et intervention d\'inspection créés.', [
                'panne' => $panne,
                'ordre_mission' => $ordreMission,
                'intervention_inspection' => $intervention,
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in PanneService::validerPanne - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    private function generateNumeroOrdre(): string
    {
        $numero = 'OM-' . now()->format('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        return $numero;
    }

    public function cloturerPanne(string $panneId): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Vérifier l'accès à la panne si école
            $query = $this->repository->query()->where('id', $panneId);
            $query = $this->applyEcoleFilterForPannes($query);
            $panneExists = $query->exists();

            if (!$panneExists) {
                return $this->errorResponse('Panne non trouvée ou accès non autorisé.', 404);
            }

            $panne = $this->repository->update($panneId, [
                'statut' => StatutPanne::CLOTUREE,
                'date_cloture' => now(),
            ]);

            DB::commit();
            return $this->successResponse('Panne clôturée avec succès.', $panne);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in PanneService::cloturerPanne - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Assigner un technicien à une panne
     */
    public function assignerTechnicien(string $panneId, string $technicienId): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Vérifier l'accès à la panne si école
            $query = $this->repository->query()->where('id', $panneId);
            $query = $this->applyEcoleFilterForPannes($query);
            $panne = $query->with(['ordreMission.interventions'])->first();

            if (!$panne) {
                return $this->errorResponse('Panne non trouvée ou accès non autorisé.', 404);
            }

            if (!$panne->ordreMission) {
                return $this->errorResponse('Cette panne n\'a pas d\'ordre de mission associé.', 400);
            }

            // Récupérer l'intervention d'inspection (ou la première intervention disponible)
            $intervention = $panne->ordreMission->interventions()->first();

            if (!$intervention) {
                return $this->errorResponse('Aucune intervention trouvée pour cette panne.', 404);
            }

            // Assigner le technicien à l'intervention via la table pivot
            $intervention->techniciens()->syncWithoutDetaching([
                $technicienId => [
                    'date_assignation' => now(),
                    'role' => 'principal'
                ]
            ]);

            // Mettre à jour le statut de l'intervention si nécessaire
            if ($intervention->statut === 'planifiee') {
                $intervention->update(['statut' => 'assignee']);
            }

            DB::commit();
            return $this->successResponse('Technicien assigné avec succès.', $intervention->load('techniciens'));
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in PanneService::assignerTechnicien - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Mettre à jour le statut d'une panne
     */
    public function updateStatut(string $panneId, string $statut): JsonResponse
    {
        try {
            // Vérifier l'accès à la panne si école
            $query = $this->repository->query()->where('id', $panneId);
            $query = $this->applyEcoleFilterForPannes($query);
            $panneExists = $query->exists();

            if (!$panneExists) {
                return $this->errorResponse('Panne non trouvée ou accès non autorisé.', 404);
            }

            $panne = $this->repository->update($panneId, [
                'statut' => $statut,
            ]);

            return $this->successResponse('Statut mis à jour avec succès.', $panne);
        } catch (Exception $e) {
            Log::error("Error in PanneService::updateStatut - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Récupérer les pannes d'une sirène
     */
    public function getPannesBySirene(string $sireneId): JsonResponse
    {
        try {
            $query = $this->repository->query()
                ->where('sirene_id', $sireneId)
                ->with(['sirene', 'site', 'ordreMission', 'interventions']);

            // Appliquer le filtre école si l'utilisateur est une école
            $query = $this->applyEcoleFilterForPannes($query);

            $pannes = $query->get();
            return $this->successResponse('Pannes récupérées avec succès.', $pannes);
        } catch (Exception $e) {
            Log::error("Error in PanneService::getPannesBySirene - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Récupérer les pannes d'une école
     */
    public function getPannesByEcole(string $ecoleId): JsonResponse
    {
        try {
            // Si l'utilisateur est une école, forcer l'ID à celui de l'utilisateur connecté
            if ($this->isEcoleUser()) {
                $ecoleId = $this->getEcoleId();
            }

            // Récupérer tous les sites de l'école
            $pannes = $this->repository->query()
                ->whereHas('site', function ($query) use ($ecoleId) {
                    $query->where('ecole_id', $ecoleId);
                })
                ->with(['sirene', 'site', 'ordreMission', 'interventions'])
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->successResponse('Pannes de l\'école récupérées avec succès.', $pannes);
        } catch (Exception $e) {
            Log::error("Error in PanneService::getPannesByEcole - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Récupérer les statistiques des pannes
     */
    public function getStatistiques(): JsonResponse
    {
        try {
            // Créer une fonction helper pour appliquer le filtre sur chaque query
            $applyFilter = function($query) {
                return $this->applyEcoleFilterForPannes($query);
            };

            $stats = [
                'total' => $applyFilter($this->repository->query())->count(),
                'en_attente' => $applyFilter($this->repository->query()->where('statut', StatutPanne::EN_ATTENTE))->count(),
                'validees' => $applyFilter($this->repository->query()->where('statut', StatutPanne::VALIDEE))->count(),
                'en_cours' => $applyFilter($this->repository->query()->where('statut', StatutPanne::EN_COURS))->count(),
                'resolues' => $applyFilter($this->repository->query()->where('statut', StatutPanne::RESOLUE))->count(),
                'cloturees' => $applyFilter($this->repository->query()->where('statut', StatutPanne::CLOTUREE))->count(),
                'par_priorite' => [
                    'haute' => $applyFilter($this->repository->query()->where('priorite', 'haute'))->count(),
                    'moyenne' => $applyFilter($this->repository->query()->where('priorite', 'moyenne'))->count(),
                    'faible' => $applyFilter($this->repository->query()->where('priorite', 'faible'))->count(),
                ],
                'recentes' => $applyFilter($this->repository->query())
                    ->with(['sirene', 'site'])
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get(),
            ];

            return $this->successResponse('Statistiques récupérées avec succès.', $stats);
        } catch (Exception $e) {
            Log::error("Error in PanneService::getStatistiques - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
