<?php

namespace App\Services\Contracts;

use Illuminate\Http\JsonResponse;

interface MissionTechnicienServiceInterface extends BaseServiceInterface
{
    /**
     * Suspendre un intervenant
     *
     * @param string $missionTechnicienId
     * @param string $motif
     * @return JsonResponse
     */
    public function suspendreIntervenant(string $missionTechnicienId, string $motif): JsonResponse;

    /**
     * Retirer un intervenant
     *
     * @param string $missionTechnicienId
     * @return JsonResponse
     */
    public function retirerIntervenant(string $missionTechnicienId): JsonResponse;
}
