<?php

namespace App\Services;

use App\Enums\StatutPanne;
use App\Models\Panne;
use App\Repositories\Contracts\OrdreMissionRepositoryInterface;
use App\Repositories\Contracts\PanneRepositoryInterface;
use App\Services\Contracts\PanneServiceInterface;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PanneService extends BaseService implements PanneServiceInterface
{
    protected OrdreMissionRepositoryInterface $ordreMissionRepository;

    public function __construct(
        PanneRepositoryInterface $repository,
        OrdreMissionRepositoryInterface $ordreMissionRepository
    ) {
        parent::__construct($repository);
        $this->ordreMissionRepository = $ordreMissionRepository;
    }

    /**
     * Override getById() pour filtrer selon le rôle de l'utilisateur
     */
    public function getById(string $id, array $columns = ['*'], array $relations = []): JsonResponse
    {
        try {
            $user = Auth::user();

            // Si l'utilisateur est admin, retourner la panne
            if ($user && $user->isAdmin()) {
                return parent::getById($id, $columns, $relations);
            }

            // Si l'utilisateur est une école, vérifier que la panne lui appartient
            if ($user && $user->isEcole()) {
                $ecole = $user->getEcole();

                if ($ecole) {
                    $panne = Panne::with($relations)
                        ->whereHas('site', function ($query) use ($ecole) {
                            $query->where('ecole_id', $ecole->id);
                        })
                        ->find($id, $columns);

                    if (!$panne) {
                        return $this->notFoundResponse('Panne non trouvée ou non accessible.');
                    }

                    return $this->successResponse(null, $panne);
                }
            }

            return $this->notFoundResponse('Panne non accessible.');
        } catch (Exception $e) {
            Log::error("Error in PanneService::getById - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function validerPanne(string $panneId, array $ordreMissionData = []): JsonResponse
    {
        try {
            DB::beginTransaction();

            $panne = $this->repository->update($panneId, [
                'statut' => StatutPanne::VALIDEE,
                'valide_par' => auth()->id(),
                'date_validation' => now(),
            ]);

            // Fetch the panne with its site relationship
            $panneWithSite = $this->repository->find($panneId, ['site']);

            // Générer le numéro d'ordre
            $numeroOrdre = $this->generateNumeroOrdre();

            // Préparer les données de l'ordre de mission
            // Le nombre_techniciens_requis doit être fourni par l'admin lors de la validation
            $ordreMissionPayload = array_merge([
                'panne_id' => $panneWithSite->id,
                'ville_id' => $panneWithSite->site->ville_id,
                'valide_par' => auth()->user()->id,
                'numero_ordre' => $numeroOrdre,
                'statut' => 'en_attente',
                'date_generation' => now(),
                'nombre_techniciens_acceptes' => 0,
            ], $ordreMissionData);

            // Create OrdreMission
            $ordreMission = $this->ordreMissionRepository->create($ordreMissionPayload);

            DB::commit();
            return $this->successResponse('Panne validée et ordre de mission créé.', [
                'panne' => $panne,
                'ordre_mission' => $ordreMission,
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in PanneService::validerPanne - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    private function generateNumeroOrdre(): string
    {
        do {
            $numero = 'OM-' . date('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(6));
        } while ($this->ordreMissionRepository->findBy(['numero_ordre' => $numero]));

        return $numero;
    }

    public function cloturerPanne(string $panneId): JsonResponse
    {
        try {
            DB::beginTransaction();

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
}
