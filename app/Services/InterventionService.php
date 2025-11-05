<?php

namespace App\Services;

use App\Enums\StatutCandidature;
use App\Enums\StatutMission;
use App\Enums\StatutOrdreMission;
use App\Models\AvisIntervention;
use App\Models\AvisRapport;
use App\Repositories\Contracts\InterventionRepositoryInterface;
use App\Repositories\Contracts\MissionTechnicienRepositoryInterface;
use App\Repositories\Contracts\RapportInterventionRepositoryInterface;
use App\Repositories\Contracts\OrdreMissionRepositoryInterface;
use App\Services\Contracts\InterventionServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class InterventionService extends BaseService implements InterventionServiceInterface
{
    protected MissionTechnicienRepositoryInterface $missionRepository;
    protected RapportInterventionRepositoryInterface $rapportRepository;
    protected OrdreMissionRepositoryInterface $ordreMissionRepository;

    public function __construct(
        InterventionRepositoryInterface $repository,
        MissionTechnicienRepositoryInterface $missionRepository,
        RapportInterventionRepositoryInterface $rapportRepository,
        OrdreMissionRepositoryInterface $ordreMissionRepository
    ) {
        parent::__construct($repository);
        $this->missionRepository = $missionRepository;
        $this->rapportRepository = $rapportRepository;
        $this->ordreMissionRepository = $ordreMissionRepository;
    }

    public function soumettreCandidatureMission(string $ordreMissionId, string $technicienId): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Récupérer l'ordre de mission
            $ordreMission = $this->ordreMissionRepository->find($ordreMissionId);
            if (!$ordreMission) {
                DB::rollBack();
                return $this->notFoundResponse('Ordre de mission non trouvé.');
            }

            // Vérifier si la candidature est ouverte
            if (!$ordreMission->candidatureOuverte()) {
                DB::rollBack();
                $message = $ordreMission->candidature_cloturee
                    ? 'Les candidatures ont été clôturées pour cette mission.'
                    : ($ordreMission->nombreTechniciensAtteint()
                        ? 'Le nombre de techniciens requis est déjà atteint.'
                        : 'La période de candidature est fermée.');
                return $this->errorResponse($message, 400);
            }

            // Vérifier si le technicien a déjà soumis une candidature
            $candidatureExistante = $this->missionRepository->findBy([
                'ordre_mission_id' => $ordreMissionId,
                'technicien_id' => $technicienId,
            ]);

            if ($candidatureExistante) {
                DB::rollBack();
                return $this->errorResponse('Vous avez déjà soumis une candidature pour cette mission.', 400);
            }

            // Créer la candidature dans missions_techniciens
            $mission = $this->missionRepository->create([
                'ordre_mission_id' => $ordreMissionId,
                'technicien_id' => $technicienId,
                'statut_candidature' => StatutCandidature::SOUMISE,
            ]);

            DB::commit();
            return $this->createdResponse($mission);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in InterventionService::soumettreCandidatureMission - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function accepterCandidature(string $missionTechnicienId, string $adminId): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Récupérer la candidature
            $missionTechnicien = $this->missionRepository->find($missionTechnicienId, relations: ['ordreMission']);
            if (!$missionTechnicien) {
                DB::rollBack();
                return $this->notFoundResponse('Candidature non trouvée.');
            }

            // Vérifier si l'ordre de mission peut encore accepter des techniciens
            $ordreMission = $missionTechnicien->ordreMission;
            if (!$ordreMission->peutAccepterTechnicien()) {
                DB::rollBack();
                return $this->errorResponse('Le nombre de techniciens requis est déjà atteint.', 400);
            }

            // Mettre à jour la candidature acceptée
            $this->missionRepository->update($missionTechnicienId, [
                'statut_candidature' => StatutCandidature::ACCEPTEE,
                'statut' => StatutMission::ACCEPTEE,
                'date_acceptation' => now(),
            ]);

            // Incrémenter le nombre de techniciens acceptés
            $nouveauNombre = $ordreMission->nombre_techniciens_acceptes + 1;
            $updateData = [
                'nombre_techniciens_acceptes' => $nouveauNombre,
            ];

            // Mettre à jour l'ordre de mission à 'en_cours' si c'est la première candidature acceptée
            if ($ordreMission->statut === StatutOrdreMission::EN_ATTENTE) {
                $updateData['statut'] = StatutOrdreMission::EN_COURS;
            }

            // Clôturer automatiquement les candidatures si le quota est atteint
            if ($nouveauNombre >= $ordreMission->nombre_techniciens_requis) {
                $updateData['candidature_cloturee'] = true;
                $updateData['date_cloture_candidature'] = now();
            }

            $this->ordreMissionRepository->update($ordreMission->id, $updateData);

            // Créer l'intervention
            $intervention = $this->repository->create([
                'panne_id' => $ordreMission->panne_id,
                'technicien_id' => $missionTechnicien->technicien_id,
                'ordre_mission_id' => $ordreMission->id,
                'statut' => 'assignee',
                'date_assignation' => now(),
            ]);

            DB::commit();
            return $this->successResponse('Candidature acceptée et intervention créée.', $intervention);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in InterventionService::accepterCandidature - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function refuserCandidature(string $missionTechnicienId, string $adminId): JsonResponse
    {
        try {
            $missionTechnicien = $this->missionRepository->update($missionTechnicienId, [
                'statut_candidature' => StatutCandidature::REFUSEE,
                'statut' => StatutMission::REFUSEE,
            ]);

            return $this->successResponse('Candidature refusée.', $missionTechnicien);
        } catch (Exception $e) {
            Log::error("Error in InterventionService::refuserCandidature - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function retirerCandidature(string $missionTechnicienId, string $motifRetrait): JsonResponse
    {
        try {
            $missionTechnicien = $this->missionRepository->update($missionTechnicienId, [
                'statut_candidature' => StatutCandidature::RETIREE,
                'statut' => StatutMission::RETIREE,
                'date_retrait' => now(),
                'motif_retrait' => $motifRetrait,
            ]);

            return $this->successResponse('Candidature retirée.', $missionTechnicien);
        } catch (Exception $e) {
            Log::error("Error in InterventionService::retirerCandidature - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function retirerMissionTechnicien(string $interventionId, string $motifRetrait, string $adminId): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Récupérer l'intervention avec ses relations
            $intervention = $this->repository->find($interventionId, relations: ['ordreMission']);
            if (!$intervention) {
                DB::rollBack();
                return $this->notFoundResponse('Intervention non trouvée.');
            }

            // Vérifier que l'intervention est terminée
            if ($intervention->statut !== 'terminee') {
                DB::rollBack();
                return $this->errorResponse('Seules les interventions terminées peuvent être retirées.', 400);
            }

            // Récupérer la mission technicien correspondante
            $missionTechnicien = $this->missionRepository->findBy([
                'ordre_mission_id' => $intervention->ordre_mission_id,
                'technicien_id' => $intervention->technicien_id,
            ]);

            if (!$missionTechnicien) {
                DB::rollBack();
                return $this->notFoundResponse('Mission technicien non trouvée.');
            }

            // Retirer la mission au technicien
            $this->missionRepository->update($missionTechnicien->id, [
                'statut_candidature' => StatutCandidature::RETIREE,
                'statut' => StatutMission::RETIREE,
                'date_retrait' => now(),
                'motif_retrait' => $motifRetrait,
            ]);

            // Décrémenter le nombre de techniciens acceptés
            $ordreMission = $intervention->ordreMission;
            if ($ordreMission->nombre_techniciens_acceptes > 0) {
                $nouveauNombre = $ordreMission->nombre_techniciens_acceptes - 1;
                $updateData = [
                    'nombre_techniciens_acceptes' => $nouveauNombre,
                ];

                // Rouvrir automatiquement les candidatures si le quota n'est plus atteint
                // et que la clôture était automatique (pas manuelle par admin)
                if ($ordreMission->candidature_cloturee &&
                    $nouveauNombre < $ordreMission->nombre_techniciens_requis &&
                    !$ordreMission->cloture_par) {
                    $updateData['candidature_cloturee'] = false;
                    $updateData['date_cloture_candidature'] = null;
                }

                $this->ordreMissionRepository->update($ordreMission->id, $updateData);
            }

            // Annuler l'intervention
            $this->repository->update($interventionId, [
                'statut' => 'annulee',
            ]);

            DB::commit();
            return $this->successResponse('Mission retirée au technicien.', [
                'intervention' => $intervention,
                'mission_technicien' => $missionTechnicien,
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in InterventionService::retirerMissionTechnicien - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function demarrerIntervention(string $interventionId): JsonResponse
    {
        try {
            $intervention = $this->repository->update($interventionId, [
                'statut' => 'en_cours',
                'date_debut' => now(),
            ]);

            return $this->successResponse('Intervention démarrée.', $intervention);
        } catch (Exception $e) {
            Log::error("Error in InterventionService::demarrerIntervention - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function redigerRapport(string $interventionId, array $rapportData): JsonResponse
    {
        try {
            DB::beginTransaction();

            $rapportData['intervention_id'] = $interventionId;
            $rapportData['date_rapport'] = now();
            $rapportData['date_soumission'] = now();
            $rapportData['statut'] = 'brouillon';

            $rapport = $this->rapportRepository->create($rapportData);

            // Terminer l'intervention
            $this->repository->update($interventionId, [
                'statut' => 'terminee',
                'date_fin' => now(),
            ]);

            DB::commit();
            return $this->createdResponse($rapport);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in InterventionService::redigerRapport - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function noterIntervention(string $interventionId, int $note, ?string $commentaire): JsonResponse
    {
        try {
            $intervention = $this->repository->update($interventionId, [
                'note_ecole' => $note,
                'commentaire_ecole' => $commentaire,
            ]);

            return $this->successResponse('Intervention notée par l\'école.', $intervention);
        } catch (Exception $e) {
            Log::error("Error in InterventionService::noterIntervention - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function noterRapport(string $rapportId, int $note, string $review): JsonResponse
    {
        try {
            $rapport = $this->rapportRepository->update($rapportId, [
                'review_note' => $note,
                'review_admin' => $review,
                'statut' => 'valide',
            ]);

            return $this->successResponse('Rapport noté par l\'admin.', $rapport);
        } catch (Exception $e) {
            Log::error("Error in InterventionService::noterRapport - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function ajouterAvisIntervention(string $interventionId, array $avisData): JsonResponse
    {
        try {
            $intervention = $this->repository->find($interventionId);
            if (!$intervention) {
                return $this->notFoundResponse('Intervention non trouvée.');
            }

            $avisData['intervention_id'] = $interventionId;
            $avisData['date_avis'] = now();

            $avis = AvisIntervention::create($avisData);

            // Mettre à jour aussi les champs directs dans intervention
            $this->repository->update($interventionId, [
                'note_ecole' => $avisData['note'],
                'commentaire_ecole' => $avisData['commentaire'] ?? null,
            ]);

            return $this->successResponse('Avis sur l\'intervention enregistré.', $avis);
        } catch (Exception $e) {
            Log::error("Error in InterventionService::ajouterAvisIntervention - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function ajouterAvisRapport(string $rapportId, array $avisData): JsonResponse
    {
        try {
            $rapport = $this->rapportRepository->find($rapportId);
            if (!$rapport) {
                return $this->notFoundResponse('Rapport non trouvé.');
            }

            $avisData['rapport_intervention_id'] = $rapportId;
            $avisData['date_evaluation'] = now();

            $avis = AvisRapport::create($avisData);

            // Mettre à jour aussi les champs directs dans rapport
            $this->rapportRepository->update($rapportId, [
                'review_note' => $avisData['note'],
                'review_admin' => $avisData['review'] ?? null,
                'statut' => $avisData['approuve'] ? 'valide' : 'rejete',
            ]);

            return $this->successResponse('Avis sur le rapport enregistré.', $avis);
        } catch (Exception $e) {
            Log::error("Error in InterventionService::ajouterAvisRapport - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getAvisIntervention(string $interventionId): JsonResponse
    {
        try {
            $avis = AvisIntervention::where('intervention_id', $interventionId)
                ->with(['ecole', 'auteur'])
                ->get();

            return $this->successResponse(null, $avis);
        } catch (Exception $e) {
            Log::error("Error in InterventionService::getAvisIntervention - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getAvisRapport(string $rapportId): JsonResponse
    {
        try {
            $avis = AvisRapport::where('rapport_intervention_id', $rapportId)
                ->with(['admin'])
                ->get();

            return $this->successResponse(null, $avis);
        } catch (Exception $e) {
            Log::error("Error in InterventionService::getAvisRapport - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
