<?php

namespace App\Services;

use App\Repositories\Contracts\SireneRepositoryInterface;
use App\Services\Contracts\SireneServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class SireneService extends BaseService implements SireneServiceInterface
{
    public function __construct(SireneRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function findByNumeroSerie(string $numeroSerie, array $relations = []): JsonResponse
    {
        try {
            $sirene = $this->repository->findByNumeroSerie($numeroSerie, $relations);
            if (!$sirene) {
                return $this->notFoundResponse('Sirène non trouvée.');
            }
            return $this->successResponse(null, $sirene);
        } catch (Exception $e) {
            Log::error("Error in " . get_class($this) . "::findByNumeroSerie - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getSirenesDisponibles(array $relations = []): JsonResponse
    {
        try {
            $sirenes = $this->repository->getSirenesDisponibles($relations);
            return $this->successResponse(null, $sirenes);
        } catch (Exception $e) {
            Log::error("Error in " . get_class($this) . "::getSirenesDisponibles - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getSirenesAvecAbonnementActif(array $relations = [], int $perPage = 15, ?string $ecoleId = null): JsonResponse
    {
        try {
            $sirenes = $this->repository->getSirenesAvecAbonnementActif($relations, $perPage, $ecoleId);

            return response()->json([
                'success' => true,
                'message' => 'Sirènes avec abonnement actif récupérées avec succès.',
                'data' => $sirenes->items(),
                'pagination' => [
                    'current_page' => $sirenes->currentPage(),
                    'per_page' => $sirenes->perPage(),
                    'total' => $sirenes->total(),
                    'last_page' => $sirenes->lastPage(),
                    'from' => $sirenes->firstItem(),
                    'to' => $sirenes->lastItem(),
                    'has_more_pages' => $sirenes->hasMorePages(),
                ],
                'links' => [
                    'first' => $sirenes->url(1),
                    'last' => $sirenes->url($sirenes->lastPage()),
                    'prev' => $sirenes->previousPageUrl(),
                    'next' => $sirenes->nextPageUrl(),
                ],
            ]);
        } catch (Exception $e) {
            Log::error("Error in " . get_class($this) . "::getSirenesAvecAbonnementActif - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function affecterSireneASite(string $sireneId, string $siteId, ?string $ecoleId = null): JsonResponse
    {
        try {
            DB::beginTransaction();
            $sirene = $this->repository->affecterSireneASite($sireneId, $siteId, $ecoleId);
            DB::commit();
            return $this->successResponse('Sirène affectée avec succès.', $sirene);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in " . get_class($this) . "::affecterSireneASite - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getProgrammationForSirene(\App\Models\Sirene $sirene): JsonResponse
    {
        try {
            // Note: L'authentification est déjà gérée par le middleware AuthenticateEsp8266
            // La sirène a déjà été validée et fournie par le middleware

            // Charger les programmations actives pour cette sirène
            $sirene->load([
                'programmations' => function ($query) {
                    $query->where('actif', true)
                        ->where('date_debut', '<=', now())
                        ->where('date_fin', '>=', now())
                        ->orderBy('created_at', 'desc');
                }
            ]);

            // Vérifier qu'il y a une programmation active
            $programmation = $sirene->programmations->first();
            if (!$programmation) {
                return $this->notFoundResponse('Aucune programmation active trouvée pour cette sirène.');
            }

            // Retourner les données de programmation
            return $this->successResponse(null, [
                'chaine_cryptee' => $programmation->chaine_cryptee,
                'chaine_programmee' => $programmation->chaine_programmee,
                'version' => '01',
                'date_generation' => $programmation->updated_at->format('Y-m-d H:i:s'),
                'date_debut' => $programmation->date_debut->format('Y-m-d'),
                'date_fin' => $programmation->date_fin->format('Y-m-d'),
            ]);

        } catch (Exception $e) {
            Log::error("Error in " . get_class($this) . "::getProgrammationForSirene - " . $e->getMessage(), [
                'sirene_id' => $sirene->id ?? null,
                'numero_serie' => $sirene->numero_serie ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->errorResponse('Erreur lors de la récupération de la programmation.', 500);
        }
    }
}
