<?php

namespace App\Services;

use App\Repositories\Contracts\MissionTechnicienRepositoryInterface;
use App\Repositories\Contracts\OrdreMissionRepositoryInterface;
use App\Services\Contracts\MissionTechnicienServiceInterface;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MissionTechnicienService extends BaseService implements MissionTechnicienServiceInterface
{
    protected OrdreMissionRepositoryInterface $ordreMissionRepository;

    public function __construct(
        MissionTechnicienRepositoryInterface $repository,
        OrdreMissionRepositoryInterface $ordreMissionRepository
    ) {
        parent::__construct($repository);
        $this->ordreMissionRepository = $ordreMissionRepository;
    }

    /**
     * Suspendre un intervenant
     *
     * @param string $missionTechnicienId
     * @param string $motif
     * @return JsonResponse
     */
    public function suspendreIntervenant(string $missionTechnicienId, string $motif): JsonResponse
    {
        try {
            DB::beginTransaction();

            $missionTechnicien = $this->repository->find($missionTechnicienId);

            if (!$missionTechnicien) {
                return $this->notFoundResponse('Mission technicien non trouvée.');
            }

            // Vérifier que l'intervenant n'est pas déjà suspendu
            if ($missionTechnicien->is_suspended) {
                return $this->errorResponse('Cet intervenant est déjà suspendu.', 422);
            }

            // Suspendre l'intervenant
            $missionTechnicien->update([
                'is_suspended' => true,
                'motif_suspension' => $motif,
                'date_suspension' => now(),
            ]);

            DB::commit();

            return $this->successResponse('Intervenant suspendu avec succès.', $missionTechnicien->fresh());
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in MissionTechnicienService::suspendreIntervenant - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Retirer un intervenant
     *
     * @param string $missionTechnicienId
     * @return JsonResponse
     */
    public function retirerIntervenant(string $missionTechnicienId): JsonResponse
    {
        try {
            DB::beginTransaction();

            $missionTechnicien = $this->repository->find($missionTechnicienId);

            if (!$missionTechnicien) {
                return $this->notFoundResponse('Mission technicien non trouvée.');
            }

            $ordreMissionId = $missionTechnicien->ordre_mission_id;

            // Soft delete de la mission technicien
            $missionTechnicien->delete();

            // Décrémenter le nombre de techniciens acceptés de l'ordre de mission
            $ordreMission = $this->ordreMissionRepository->find($ordreMissionId);
            if ($ordreMission && $ordreMission->nombre_techniciens_acceptes > 0) {
                $ordreMission->update([
                    'nombre_techniciens_acceptes' => $ordreMission->nombre_techniciens_acceptes - 1,
                ]);
            }

            DB::commit();

            return $this->successResponse('Intervenant retiré avec succès.', null);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in MissionTechnicienService::retirerIntervenant - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
