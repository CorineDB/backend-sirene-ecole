<?php

namespace App\Services;

use App\Enums\StatutPanne;
use App\Repositories\Contracts\InterventionRepositoryInterface;
use App\Repositories\Contracts\OrdreMissionRepositoryInterface;
use App\Repositories\Contracts\PanneRepositoryInterface;
use App\Services\Contracts\PanneServiceInterface;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PanneService extends BaseService implements PanneServiceInterface
{
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
