<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Contracts\InterventionServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use OpenApi\Annotations as OA;

class InterventionController extends Controller
{
    protected InterventionServiceInterface $interventionService;

    public function __construct(InterventionServiceInterface $interventionService)
    {
        $this->middleware('auth:api');
        $this->interventionService = $interventionService;
    }

    /**
     * Lister toutes les interventions
     *
     * @OA\Get(
     *     path="/api/interventions",
     *     tags={"Pannes & Interventions"},
     *     summary="Liste de toutes les interventions",
     *     description="Récupère la liste paginée de toutes les interventions avec techniciens, pannes et ordres de mission",
     *     operationId="getAllInterventions",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Nombre de résultats par page",
     *         @OA\Schema(type="integer", default=15, example=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des interventions récupérée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('voir_les_interventions');
        $perPage = $request->get('per_page', 15);
        return $this->interventionService->getAll($perPage, ['technicien', 'panne', 'ordreMission']);
    }

    /**
     * Afficher les détails d'une intervention
     *
     * @OA\Get(
     *     path="/api/interventions/{id}",
     *     tags={"Pannes & Interventions"},
     *     summary="Détails d'une intervention",
     *     description="Récupère les détails complets d'une intervention avec le technicien, la panne et le rapport",
     *     operationId="getInterventionDetails",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de l'intervention",
     *         @OA\Schema(type="string", example="01ARZ3NDEKTSV4RRFFQ69G5FAV")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Détails de l'intervention récupérés avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function show(string $id): JsonResponse
    {
        Gate::authorize('voir_intervention');
        return $this->interventionService->getById($id, ['technicien', 'panne', 'rapport']);
    }

    /**
     * Soumettre une candidature pour un ordre de mission
     *
     * @OA\Post(
     *     path="/api/interventions/ordres-mission/{ordreMissionId}/candidature",
     *     tags={"Pannes & Interventions"},
     *     summary="Soumettre une candidature",
     *     description="Permet à un technicien de soumettre sa candidature pour un ordre de mission",
     *     operationId="soumettreCandidature",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="ordreMissionId",
     *         in="path",
     *         required=true,
     *         description="ID de l'ordre de mission",
     *         @OA\Schema(type="string", example="01ARZ3NDEKTSV4RRFFQ69G5FAV")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"technicien_id"},
     *             @OA\Property(property="technicien_id", type="string", example="01ARZ3NDEKTSV4RRFFQ69G5FAV", description="ID du technicien candidat")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Candidature soumise avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Candidature soumise avec succès")
     *         )
     *     )
     * )
     */
    public function soumettreCandidature(Request $request, string $ordreMissionId): JsonResponse
    {
        Gate::authorize('creer_mission_technicien');
        $validated = $request->validate([
            'technicien_id' => 'required|string|exists:techniciens,id',
        ]);

        return $this->interventionService->soumettreCandidatureMission($ordreMissionId, $validated['technicien_id']);
    }

    /**
     * Accepter une candidature
     *
     * @OA\Put(
     *     path="/api/interventions/candidatures/{missionTechnicienId}/accepter",
     *     tags={"Pannes & Interventions"},
     *     summary="Accepter une candidature",
     *     description="Accepte la candidature d'un technicien et crée une intervention (administrateur uniquement)",
     *     operationId="accepterCandidature",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="missionTechnicienId",
     *         in="path",
     *         required=true,
     *         description="ID de la candidature (mission_technicien)",
     *         @OA\Schema(type="string", example="01ARZ3NDEKTSV4RRFFQ69G5FAV")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"admin_id"},
     *             @OA\Property(property="admin_id", type="string", example="01ARZ3NDEKTSV4RRFFQ69G5FAV", description="ID de l'administrateur")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Candidature acceptée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Candidature acceptée")
     *         )
     *     )
     * )
     */
    public function accepterCandidature(Request $request, string $missionTechnicienId): JsonResponse
    {
        Gate::authorize('assigner_technicien_intervention');
        $validated = $request->validate([
            'admin_id' => 'required|string|exists:users,id',
        ]);

        return $this->interventionService->accepterCandidature($missionTechnicienId, $validated['admin_id']);
    }

    /**
     * Refuser une candidature
     *
     * @OA\Put(
     *     path="/api/interventions/candidatures/{missionTechnicienId}/refuser",
     *     tags={"Pannes & Interventions"},
     *     summary="Refuser une candidature",
     *     description="Refuse la candidature d'un technicien (administrateur uniquement)",
     *     operationId="refuserCandidature",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="missionTechnicienId",
     *         in="path",
     *         required=true,
     *         description="ID de la candidature",
     *         @OA\Schema(type="string", example="01ARZ3NDEKTSV4RRFFQ69G5FAV")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"admin_id"},
     *             @OA\Property(property="admin_id", type="string", example="01ARZ3NDEKTSV4RRFFQ69G5FAV", description="ID de l'administrateur")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Candidature refusée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Candidature refusée")
     *         )
     *     )
     * )
     */
    public function refuserCandidature(Request $request, string $missionTechnicienId): JsonResponse
    {
        Gate::authorize('modifier_mission_technicien');
        $validated = $request->validate([
            'admin_id' => 'required|string|exists:users,id',
        ]);

        return $this->interventionService->refuserCandidature($missionTechnicienId, $validated['admin_id']);
    }

    /**
     * Retirer une candidature
     *
     * @OA\Put(
     *     path="/api/interventions/candidatures/{missionTechnicienId}/retirer",
     *     tags={"Pannes & Interventions"},
     *     summary="Retirer sa candidature",
     *     description="Permet à un technicien de retirer sa propre candidature",
     *     operationId="retirerCandidature",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="missionTechnicienId",
     *         in="path",
     *         required=true,
     *         description="ID de la candidature",
     *         @OA\Schema(type="string", example="01ARZ3NDEKTSV4RRFFQ69G5FAV")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"motif_retrait"},
     *             @OA\Property(property="motif_retrait", type="string", example="Indisponibilité imprévue", description="Motif du retrait")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Candidature retirée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Candidature retirée")
     *         )
     *     )
     * )
     */
    public function retirerCandidature(Request $request, string $missionTechnicienId): JsonResponse
    {
        Gate::authorize('modifier_mission_technicien');
        $validated = $request->validate([
            'motif_retrait' => 'required|string',
        ]);

        return $this->interventionService->retirerCandidature($missionTechnicienId, $validated['motif_retrait']);
    }

    /**
     * Créer une intervention manuellement (sans passer par les candidatures)
     */
    public function creerIntervention(Request $request, string $ordreMissionId): JsonResponse
    {
        Gate::authorize('creer_intervention');
        $validated = $request->validate([
            'type_intervention' => 'nullable|string|in:constat,reparation,installation,maintenance,autre',
            'nombre_techniciens_requis' => 'nullable|integer|min:1',
            'date_intervention' => 'nullable|date',
            'instructions' => 'nullable|string',
            'lieu_rdv' => 'nullable|string',
            'heure_rdv' => 'nullable|date_format:H:i',
            'technicien_ids' => 'nullable|array',
            'technicien_ids.*' => 'string|exists:techniciens,id',
        ]);

        return $this->interventionService->creerIntervention($ordreMissionId, $validated);
    }

    /**
     * Assigner un technicien à une intervention (même si démarrée)
     */
    public function assignerTechnicien(Request $request, string $interventionId): JsonResponse
    {
        Gate::authorize('assigner_technicien_intervention');
        $validated = $request->validate([
            'technicien_id' => 'required|string|exists:techniciens,id',
            'role' => 'nullable|string',
        ]);

        return $this->interventionService->assignerTechnicien(
            $interventionId,
            $validated['technicien_id'],
            $validated['role'] ?? null
        );
    }

    /**
     * Retirer un technicien d'une intervention
     */
    public function retirerTechnicien(Request $request, string $interventionId): JsonResponse
    {
        Gate::authorize('modifier_intervention');
        $validated = $request->validate([
            'technicien_id' => 'required|string|exists:techniciens,id',
        ]);

        return $this->interventionService->retirerTechnicien($interventionId, $validated['technicien_id']);
    }

    /**
     * Planifier une intervention (date, instructions, lieu/heure rdv)
     */
    public function planifierIntervention(Request $request, string $interventionId): JsonResponse
    {
        Gate::authorize('modifier_intervention');
        $validated = $request->validate([
            'date_intervention' => 'nullable|date',
            'instructions' => 'nullable|string',
            'lieu_rdv' => 'nullable|string',
            'heure_rdv' => 'nullable|date_format:H:i',
        ]);

        return $this->interventionService->planifierIntervention($interventionId, $validated);
    }

    /**
     * Retirer un technicien d'une mission
     *
     * @OA\Put(
     *     path="/api/interventions/{interventionId}/retirer-mission",
     *     tags={"Pannes & Interventions"},
     *     summary="Retirer un technicien d'une mission",
     *     description="Retire un technicien d'une intervention en cours (administrateur uniquement)",
     *     operationId="retirerMission",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="interventionId",
     *         in="path",
     *         required=true,
     *         description="ID de l'intervention",
     *         @OA\Schema(type="string", example="01ARZ3NDEKTSV4RRFFQ69G5FAV")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"motif_retrait", "admin_id"},
     *             @OA\Property(property="motif_retrait", type="string", example="Performance insuffisante", description="Motif du retrait"),
     *             @OA\Property(property="admin_id", type="string", example="01ARZ3NDEKTSV4RRFFQ69G5FAV", description="ID de l'administrateur")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Technicien retiré avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Technicien retiré de la mission")
     *         )
     *     )
     * )
     */
    public function retirerMission(Request $request, string $interventionId): JsonResponse
    {
        Gate::authorize('modifier_intervention');
        $validated = $request->validate([
            'motif_retrait' => 'required|string',
            'admin_id' => 'required|string|exists:users,id',
        ]);

        return $this->interventionService->retirerMissionTechnicien(
            $interventionId,
            $validated['motif_retrait'],
            $validated['admin_id']
        );
    }

    /**
     * Démarrer une intervention
     *
     * @OA\Put(
     *     path="/api/interventions/{interventionId}/demarrer",
     *     tags={"Pannes & Interventions"},
     *     summary="Démarrer une intervention",
     *     description="Marque le début d'une intervention par le technicien",
     *     operationId="demarrerIntervention",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="interventionId",
     *         in="path",
     *         required=true,
     *         description="ID de l'intervention",
     *         @OA\Schema(type="string", example="01ARZ3NDEKTSV4RRFFQ69G5FAV")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Intervention démarrée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Intervention démarrée"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="statut", type="string", example="en_cours"),
     *                 @OA\Property(property="date_debut", type="string", format="date-time")
     *             )
     *         )
     *     )
     * )
     */
    public function demarrer(string $interventionId): JsonResponse
    {
        Gate::authorize('modifier_intervention');
        return $this->interventionService->demarrerIntervention($interventionId);
    }

    /**
     * Terminer une intervention
     *
     * @OA\Put(
     *     path="/api/interventions/{interventionId}/terminer",
     *     tags={"Pannes & Interventions"},
     *     summary="Terminer une intervention",
     *     description="Marque la fin d'une intervention par le technicien",
     *     operationId="terminerIntervention",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="interventionId",
     *         in="path",
     *         required=true,
     *         description="ID de l'intervention",
     *         @OA\Schema(type="string", example="01ARZ3NDEKTSV4RRFFQ69G5FAV")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Intervention terminée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Intervention terminée"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="statut", type="string", example="termine"),
     *                 @OA\Property(property="date_fin", type="string", format="date-time")
     *             )
     *         )
     *     )
     * )
     */
    public function terminer(string $interventionId): JsonResponse
    {
        Gate::authorize('modifier_intervention');
        return $this->interventionService->terminerIntervention($interventionId);
    }

    /**
     * Rédiger un rapport d'intervention
     *
     * @OA\Post(
     *     path="/api/interventions/{interventionId}/rapport",
     *     tags={"Pannes & Interventions"},
     *     summary="Rédiger un rapport d'intervention",
     *     description="Permet au technicien de rédiger un rapport détaillé après l'intervention",
     *     operationId="redigerRapport",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="interventionId",
     *         in="path",
     *         required=true,
     *         description="ID de l'intervention",
     *         @OA\Schema(type="string", example="01ARZ3NDEKTSV4RRFFQ69G5FAV")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Contenu du rapport d'intervention",
     *         @OA\JsonContent(
     *             required={"rapport", "resultat"},
     *             @OA\Property(property="rapport", type="string", example="Intervention effectuée avec succès...", description="Texte du rapport"),
     *             @OA\Property(property="diagnostic", type="string", example="Court-circuit du module principal", description="Diagnostic de la panne"),
     *             @OA\Property(property="travaux_effectues", type="string", example="Remplacement du module électrique", description="Description des travaux"),
     *             @OA\Property(property="pieces_utilisees", type="string", example="Module électrique XYZ-123", description="Pièces remplacées"),
     *             @OA\Property(property="resultat", type="string", enum={"resolu", "partiellement_resolu", "non_resolu"}, example="resolu", description="Résultat de l'intervention"),
     *             @OA\Property(property="recommandations", type="string", example="Vérifier le système tous les 6 mois", description="Recommandations"),
     *             @OA\Property(property="photos", type="array", @OA\Items(type="string"), description="URLs des photos")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Rapport créé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Rapport créé avec succès"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function redigerRapport(Request $request, string $interventionId): JsonResponse
    {
        Gate::authorize('creer_rapport_intervention');
        $validated = $request->validate([
            'rapport' => 'required|string',
            'diagnostic' => 'nullable|string',
            'travaux_effectues' => 'nullable|string',
            'pieces_utilisees' => 'nullable|string',
            'resultat' => 'required|in:resolu,partiellement_resolu,non_resolu',
            'recommandations' => 'nullable|string',
            'photos' => 'nullable|array',
        ]);

        return $this->interventionService->redigerRapport($interventionId, $validated);
    }

    /**
     * Noter une intervention
     *
     * @OA\Put(
     *     path="/api/interventions/{interventionId}/noter",
     *     tags={"Pannes & Interventions"},
     *     summary="Noter une intervention",
     *     description="Permet à l'école de noter la qualité de l'intervention (note simple, ancien système)",
     *     operationId="noterIntervention",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="interventionId",
     *         in="path",
     *         required=true,
     *         description="ID de l'intervention",
     *         @OA\Schema(type="string", example="01ARZ3NDEKTSV4RRFFQ69G5FAV")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"note"},
     *             @OA\Property(property="note", type="integer", minimum=1, maximum=5, example=4, description="Note de 1 à 5 étoiles"),
     *             @OA\Property(property="commentaire", type="string", example="Excellent travail", description="Commentaire optionnel")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Note enregistrée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Note enregistrée")
     *         )
     *     )
     * )
     */
    public function noterIntervention(Request $request, string $interventionId): JsonResponse
    {
        Gate::authorize('creer_avis_intervention');
        $validated = $request->validate([
            'note' => 'required|integer|min:1|max:5',
            'commentaire' => 'nullable|string',
        ]);

        return $this->interventionService->noterIntervention(
            $interventionId,
            $validated['note'],
            $validated['commentaire'] ?? null
        );
    }

    /**
     * Noter un rapport d'intervention
     *
     * @OA\Put(
     *     path="/api/interventions/rapports/{rapportId}/noter",
     *     tags={"Pannes & Interventions"},
     *     summary="Noter un rapport",
     *     description="Permet à l'administrateur de noter la qualité d'un rapport d'intervention (ancien système)",
     *     operationId="noterRapport",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="rapportId",
     *         in="path",
     *         required=true,
     *         description="ID du rapport",
     *         @OA\Schema(type="string", example="01ARZ3NDEKTSV4RRFFQ69G5FAV")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"note", "review"},
     *             @OA\Property(property="note", type="integer", minimum=1, maximum=5, example=5, description="Note de 1 à 5"),
     *             @OA\Property(property="review", type="string", example="Rapport complet et bien rédigé", description="Commentaire sur le rapport")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Note du rapport enregistrée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Note enregistrée")
     *         )
     *     )
     * )
     */
    public function noterRapport(Request $request, string $rapportId): JsonResponse
    {
        Gate::authorize('creer_avis_rapport');
        $validated = $request->validate([
            'note' => 'required|integer|min:1|max:5',
            'review' => 'required|string',
        ]);

        return $this->interventionService->noterRapport($rapportId, $validated['note'], $validated['review']);
    }

    /**
     * Ajouter un avis détaillé sur une intervention
     *
     * @OA\Post(
     *     path="/api/interventions/{interventionId}/avis",
     *     tags={"Pannes & Interventions"},
     *     summary="Ajouter un avis détaillé sur une intervention",
     *     description="Permet à l'école d'ajouter un avis détaillé avec plusieurs critères d'évaluation (nouveau système d'avis)",
     *     operationId="ajouterAvisIntervention",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="interventionId",
     *         in="path",
     *         required=true,
     *         description="ID de l'intervention",
     *         @OA\Schema(type="string", example="01ARZ3NDEKTSV4RRFFQ69G5FAV")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Avis détaillé sur l'intervention",
     *         @OA\JsonContent(
     *             required={"ecole_id", "note"},
     *             @OA\Property(property="ecole_id", type="string", example="01ARZ3NDEKTSV4RRFFQ69G5FAV", description="ID de l'école"),
     *             @OA\Property(property="auteur_id", type="string", example="01ARZ3NDEKTSV4RRFFQ69G5FAV", description="ID de l'utilisateur auteur"),
     *             @OA\Property(property="note", type="integer", minimum=1, maximum=5, example=5, description="Note globale"),
     *             @OA\Property(property="commentaire", type="string", example="Excellent travail, très professionnel", description="Commentaire détaillé"),
     *             @OA\Property(property="type_avis", type="string", enum={"satisfaction", "qualite_travail", "professionnalisme", "delai", "proprete"}, example="satisfaction", description="Type d'avis"),
     *             @OA\Property(property="recommande", type="boolean", example=true, description="Recommande ce technicien")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Avis ajouté avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Avis ajouté avec succès"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function ajouterAvisIntervention(Request $request, string $interventionId): JsonResponse
    {
        Gate::authorize('creer_avis_intervention');
        $validated = $request->validate([
            'ecole_id' => 'required|string|exists:ecoles,id',
            'auteur_id' => 'nullable|string|exists:users,id',
            'note' => 'required|integer|min:1|max:5',
            'commentaire' => 'nullable|string',
            'type_avis' => 'nullable|string|in:satisfaction,qualite_travail,professionnalisme,delai,proprete',
            'recommande' => 'nullable|boolean',
        ]);

        return $this->interventionService->ajouterAvisIntervention($interventionId, $validated);
    }

    /**
     * Ajouter un avis détaillé sur un rapport
     *
     * @OA\Post(
     *     path="/api/interventions/rapports/{rapportId}/avis",
     *     tags={"Pannes & Interventions"},
     *     summary="Ajouter un avis détaillé sur un rapport",
     *     description="Permet à l'administrateur d'ajouter un avis détaillé sur la qualité d'un rapport (nouveau système d'avis)",
     *     operationId="ajouterAvisRapport",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="rapportId",
     *         in="path",
     *         required=true,
     *         description="ID du rapport",
     *         @OA\Schema(type="string", example="01ARZ3NDEKTSV4RRFFQ69G5FAV")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Avis détaillé sur le rapport",
     *         @OA\JsonContent(
     *             required={"admin_id", "note"},
     *             @OA\Property(property="admin_id", type="string", example="01ARZ3NDEKTSV4RRFFQ69G5FAV", description="ID de l'administrateur"),
     *             @OA\Property(property="note", type="integer", minimum=1, maximum=5, example=5, description="Note globale du rapport"),
     *             @OA\Property(property="review", type="string", example="Rapport très détaillé et complet", description="Commentaire sur le rapport"),
     *             @OA\Property(property="type_evaluation", type="string", enum={"completude", "clarte", "precision", "conformite"}, example="completude", description="Type d'évaluation"),
     *             @OA\Property(property="approuve", type="boolean", example=true, description="Rapport approuvé"),
     *             @OA\Property(property="points_forts", type="string", example="Diagnostic précis, photos claires", description="Points forts du rapport"),
     *             @OA\Property(property="points_amelioration", type="string", example="Ajouter plus de détails sur les pièces", description="Points à améliorer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Avis ajouté avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Avis ajouté avec succès"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function ajouterAvisRapport(Request $request, string $rapportId): JsonResponse
    {
        Gate::authorize('creer_avis_rapport');
        $validated = $request->validate([
            'admin_id' => 'required|string|exists:users,id',
            'note' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string',
            'type_evaluation' => 'nullable|string|in:completude,clarte,precision,conformite',
            'approuve' => 'nullable|boolean',
            'points_forts' => 'nullable|string',
            'points_amelioration' => 'nullable|string',
        ]);

        return $this->interventionService->ajouterAvisRapport($rapportId, $validated);
    }

    /**
     * Récupérer les avis d'une intervention
     *
     * @OA\Get(
     *     path="/api/interventions/{interventionId}/avis",
     *     tags={"Pannes & Interventions"},
     *     summary="Récupérer les avis d'une intervention",
     *     description="Récupère tous les avis détaillés laissés par les écoles sur une intervention",
     *     operationId="getAvisIntervention",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="interventionId",
     *         in="path",
     *         required=true,
     *         description="ID de l'intervention",
     *         @OA\Schema(type="string", example="01ARZ3NDEKTSV4RRFFQ69G5FAV")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des avis récupérée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="string"),
     *                     @OA\Property(property="note", type="integer", example=5),
     *                     @OA\Property(property="commentaire", type="string"),
     *                     @OA\Property(property="type_avis", type="string"),
     *                     @OA\Property(property="recommande", type="boolean"),
     *                     @OA\Property(property="created_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getAvisIntervention(string $interventionId): JsonResponse
    {
        Gate::authorize('voir_les_avis_intervention');
        return $this->interventionService->getAvisIntervention($interventionId);
    }

    /**
     * Récupérer les avis d'un rapport
     *
     * @OA\Get(
     *     path="/api/interventions/rapports/{rapportId}/avis",
     *     tags={"Pannes & Interventions"},
     *     summary="Récupérer les avis d'un rapport",
     *     description="Récupère tous les avis détaillés laissés par les administrateurs sur un rapport d'intervention",
     *     operationId="getAvisRapport",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="rapportId",
     *         in="path",
     *         required=true,
     *         description="ID du rapport",
     *         @OA\Schema(type="string", example="01ARZ3NDEKTSV4RRFFQ69G5FAV")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des avis récupérée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="string"),
     *                     @OA\Property(property="note", type="integer", example=5),
     *                     @OA\Property(property="review", type="string"),
     *                     @OA\Property(property="type_evaluation", type="string"),
     *                     @OA\Property(property="approuve", type="boolean"),
     *                     @OA\Property(property="points_forts", type="string"),
     *                     @OA\Property(property="points_amelioration", type="string"),
     *                     @OA\Property(property="created_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getAvisRapport(string $rapportId): JsonResponse
    {
        Gate::authorize('voir_les_avis_rapport');
        return $this->interventionService->getAvisRapport($rapportId);
    }
}
