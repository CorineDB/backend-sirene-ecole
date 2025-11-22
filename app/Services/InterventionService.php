<?php

namespace App\Services;

use App\Enums\StatutCandidature;
use App\Enums\StatutMission;
use App\Enums\StatutOrdreMission;
use App\Models\AvisIntervention;
use App\Models\AvisRapport;
use App\Models\User;
use App\Models\Ecole;
use App\Notifications\AdminCandidatureSubmissionNotification;
use App\Notifications\MissionCompletionNotification;
use App\Repositories\Contracts\InterventionRepositoryInterface;
use App\Repositories\Contracts\MissionTechnicienRepositoryInterface;
use App\Repositories\Contracts\RapportInterventionRepositoryInterface;
use App\Repositories\Contracts\OrdreMissionRepositoryInterface;
use App\Services\Contracts\InterventionServiceInterface;
use App\Traits\FiltersByEcole;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class InterventionService extends BaseService implements InterventionServiceInterface
{
    use FiltersByEcole;

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

    /**
     * Surcharge de getAll pour filtrer par école si nécessaire
     */
    public function getAll(int $perPage = 15, array $relations = []): JsonResponse
    {
        try {
            $query = $this->repository->query();

            // Appliquer le filtre école si l'utilisateur est une école
            $query = $this->applyEcoleFilterForInterventions($query);

            if (!empty($relations)) {
                $query->with($relations);
            }

            $data = $query->orderBy('created_at', 'desc')->paginate($perPage);
            return $this->successResponse('Données récupérées avec succès.', $data);
        } catch (Exception $e) {
            Log::error("Error in InterventionService::getAll - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Surcharge de getById pour vérifier l'accès si école
     */
    public function getById(string $id, array $relations = []): JsonResponse
    {
        try {
            $query = $this->repository->query()->where('id', $id);

            // Appliquer le filtre école si l'utilisateur est une école
            $query = $this->applyEcoleFilterForInterventions($query);

            if (!empty($relations)) {
                $query->with($relations);
            }

            $data = $query->first();

            if (!$data) {
                return $this->errorResponse('Intervention non trouvée ou accès non autorisé.', 404);
            }

            return $this->successResponse('Donnée récupérée avec succès.', $data);
        } catch (Exception $e) {
            Log::error("Error in InterventionService::getById - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
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

            // Envoyer la notification à l'admin
            $adminUsers = User::whereHas('roles', function ($query) {
                $query->where('name', 'admin');
            })->get();

            foreach ($adminUsers as $admin) {
                $admin->notify(new AdminCandidatureSubmissionNotification([
                    'technicien_id' => $technicienId,
                    'technicien_nom' => $mission->technicien->user->full_name ?? 'Technicien Inconnu',
                    'ordre_mission_id' => $ordreMissionId,
                    'numero_ordre' => $ordreMission->numero_ordre,
                ]));
            }

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

            // Vérifier si une intervention existe déjà pour cet ordre de mission
            $intervention = $ordreMission->interventions()->first();

            if (!$intervention) {
                // Créer UNE intervention pour tout l'ordre de mission
                $intervention = $this->repository->create([
                    'panne_id' => $ordreMission->panne_id,
                    'ordre_mission_id' => $ordreMission->id,
                    'statut' => 'assignee',
                    'date_assignation' => now(),
                ]);
            }

            // Assigner le technicien à l'intervention via la table pivot
            $intervention->techniciens()->attach($missionTechnicien->technicien_id, [
                'date_assignation' => now(),
                'role' => null, // Peut être défini plus tard
                'notes' => null,
            ]);

            // Recharger l'intervention avec les techniciens
            $intervention->load('techniciens');

            DB::commit();
            return $this->successResponse('Candidature acceptée et technicien assigné à l\'intervention.', $intervention);
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


    public function terminerIntervention(string $interventionId): JsonResponse
    {
        try {
            $intervention = $this->repository->update($interventionId, [
                'statut' => 'en_cours',
                'date_debut' => now(),
            ]);

            return $this->successResponse('Intervention terminée.', $intervention);
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

            // Récupérer l'intervention mise à jour avec ses relations
            $intervention = $this->repository->find($interventionId, relations: ['ordreMission.ecole']);

            // Vérifier si toutes les interventions de cet ordre de mission sont terminées
            if ($intervention && $intervention->ordreMission) {
                $allInterventionsTerminated = $this->repository->model->where('ordre_mission_id', $intervention->ordreMission->id)
                    ->where('statut', '!=', 'terminee')
                    ->doesntExist();

                if ($allInterventionsTerminated) {
                    // Notifier l'école de la fin de la mission
                    $intervention->ordreMission->ecole->notify(new MissionCompletionNotification([
                        'id' => $intervention->ordreMission->id,
                        'numero_ordre' => $intervention->ordreMission->numero_ordre,
                        'ecole_nom' => $intervention->ordreMission->ecole->nom,
                    ]));
                }
            }

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

    /**
     * Créer une intervention manuellement (sans passer par les candidatures)
     * L'admin peut créer une intervention et y assigner des techniciens directement
     */
    public function creerIntervention(string $ordreMissionId, array $data): JsonResponse
    {
        try {
            DB::beginTransaction();

            $ordreMission = $this->ordreMissionRepository->find($ordreMissionId, relations: ['panne']);
            if (!$ordreMission) {
                DB::rollBack();
                return $this->notFoundResponse('Ordre de mission non trouvé.');
            }

            // Créer l'intervention (plusieurs interventions possibles par ordre de mission)
            $intervention = $this->repository->create([
                'panne_id' => $ordreMission->panne_id,
                'ordre_mission_id' => $ordreMissionId,
                'type_intervention' => $data['type_intervention'] ?? 'reparation',
                'nombre_techniciens_requis' => $data['nombre_techniciens_requis'] ?? null,
                'date_intervention' => $data['date_intervention'] ?? null,
                'instructions' => $data['instructions'] ?? null,
                'lieu_rdv' => $data['lieu_rdv'] ?? null,
                'heure_rdv' => $data['heure_rdv'] ?? null,
                'statut' => 'planifiee',
                'date_assignation' => now(),
            ]);

            // Assigner les techniciens si fournis
            if (!empty($data['technicien_ids'])) {
                foreach ($data['technicien_ids'] as $technicienId) {
                    $intervention->techniciens()->attach($technicienId, [
                        'id' => (string) \Illuminate\Support\Str::ulid(),
                        'date_assignation' => now(),
                    ]);
                }
            }

            DB::commit();
            return $this->createdResponse($intervention->load('techniciens'));
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in InterventionService::creerIntervention - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Assigner un technicien à une intervention
     * Peut être fait même après le démarrage de l'intervention
     */
    public function assignerTechnicien(string $interventionId, string $technicienId, ?string $role = null): JsonResponse
    {
        try {
            DB::beginTransaction();

            $intervention = $this->repository->find($interventionId);
            if (!$intervention) {
                DB::rollBack();
                return $this->notFoundResponse('Intervention non trouvée.');
            }

            // Vérifier si le technicien n'est pas déjà assigné
            if ($intervention->techniciens()->where('technicien_id', $technicienId)->exists()) {
                DB::rollBack();
                return $this->errorResponse('Ce technicien est déjà assigné à cette intervention.', 400);
            }

            // Assigner le technicien
            $intervention->techniciens()->attach($technicienId, [
                'date_assignation' => now(),
                'role' => $role,
                'notes' => null,
            ]);

            // Mettre à jour le compteur dans l'ordre de mission si lié
            if ($intervention->ordre_mission_id) {
                $ordreMission = $this->ordreMissionRepository->find($intervention->ordre_mission_id);
                if ($ordreMission) {
                    $this->ordreMissionRepository->update($ordreMission->id, [
                        'nombre_techniciens_acceptes' => $ordreMission->nombre_techniciens_acceptes + 1,
                    ]);
                }
            }

            DB::commit();
            return $this->successResponse('Technicien assigné à l\'intervention.', $intervention->load('techniciens'));
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in InterventionService::assignerTechnicien - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Retirer un technicien d'une intervention
     */
    public function retirerTechnicien(string $interventionId, string $technicienId): JsonResponse
    {
        try {
            DB::beginTransaction();

            $intervention = $this->repository->find($interventionId);
            if (!$intervention) {
                DB::rollBack();
                return $this->notFoundResponse('Intervention non trouvée.');
            }

            // Vérifier si le technicien est assigné
            if (!$intervention->techniciens()->where('technicien_id', $technicienId)->exists()) {
                DB::rollBack();
                return $this->errorResponse('Ce technicien n\'est pas assigné à cette intervention.', 400);
            }

            // Retirer le technicien
            $intervention->techniciens()->detach($technicienId);

            // Mettre à jour le compteur dans l'ordre de mission si lié
            if ($intervention->ordre_mission_id) {
                $ordreMission = $this->ordreMissionRepository->find($intervention->ordre_mission_id);
                if ($ordreMission && $ordreMission->nombre_techniciens_acceptes > 0) {
                    $this->ordreMissionRepository->update($ordreMission->id, [
                        'nombre_techniciens_acceptes' => $ordreMission->nombre_techniciens_acceptes - 1,
                    ]);
                }
            }

            DB::commit();
            return $this->successResponse('Technicien retiré de l\'intervention.', $intervention->load('techniciens'));
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in InterventionService::retirerTechnicien - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Planifier une intervention (définir date, instructions, lieu RDV)
     */
    public function planifierIntervention(string $interventionId, array $data): JsonResponse
    {
        try {
            $intervention = $this->repository->update($interventionId, [
                'date_intervention' => $data['date_intervention'] ?? null,
                'instructions' => $data['instructions'] ?? null,
                'lieu_rdv' => $data['lieu_rdv'] ?? null,
                'heure_rdv' => $data['heure_rdv'] ?? null,
            ]);

            return $this->successResponse('Intervention planifiée.', $intervention);
        } catch (Exception $e) {
            Log::error("Error in InterventionService::planifierIntervention - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
