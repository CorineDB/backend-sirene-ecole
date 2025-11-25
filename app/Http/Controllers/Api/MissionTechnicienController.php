<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Contracts\MissionTechnicienServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use OpenApi\Annotations as OA;

class MissionTechnicienController extends Controller
{
    protected MissionTechnicienServiceInterface $missionTechnicienService;

    public function __construct(MissionTechnicienServiceInterface $missionTechnicienService)
    {
        $this->missionTechnicienService = $missionTechnicienService;
    }

    /**
     * Suspendre un intervenant
     *
     * @OA\Post(
     *     path="/api/missions-techniciens/{missionTechnicienId}/suspendre",
     *     tags={"Missions Techniciens"},
     *     summary="Suspendre un intervenant",
     *     description="Suspendre un intervenant d'une mission",
     *     operationId="suspendreIntervenant",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="missionTechnicienId",
     *         in="path",
     *         required=true,
     *         description="ID de la mission technicien",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"motif"},
     *             @OA\Property(property="motif", type="string", description="Motif de la suspension")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Intervenant suspendu avec succès"
     *     )
     * )
     */
    public function suspendre(Request $request, string $missionTechnicienId): JsonResponse
    {
        Gate::authorize('modifier_mission_technicien');

        $validated = $request->validate([
            'motif' => 'required|string',
        ]);

        return $this->missionTechnicienService->suspendreIntervenant($missionTechnicienId, $validated['motif']);
    }

    /**
     * Retirer un intervenant
     *
     * @OA\Delete(
     *     path="/api/missions-techniciens/{missionTechnicienId}",
     *     tags={"Missions Techniciens"},
     *     summary="Retirer un intervenant",
     *     description="Retirer un intervenant d'une mission",
     *     operationId="retirerIntervenant",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="missionTechnicienId",
     *         in="path",
     *         required=true,
     *         description="ID de la mission technicien",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Intervenant retiré avec succès"
     *     )
     * )
     */
    public function retirer(string $missionTechnicienId): JsonResponse
    {
        Gate::authorize('modifier_mission_technicien');
        return $this->missionTechnicienService->retirerIntervenant($missionTechnicienId);
    }
}
