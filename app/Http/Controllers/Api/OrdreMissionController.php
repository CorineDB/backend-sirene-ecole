<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Contracts\OrdreMissionServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="OrdreMission",
 *     title="Ordre de Mission",
 *     description="Détails d'un ordre de mission",
 *     @OA\Property(property="id", type="string", format="ulid", description="ID de l'ordre de mission"),
 *     @OA\Property(property="panne_id", type="string", format="ulid", description="ID de la panne associée"),
 *     @OA\Property(property="ville_id", type="string", format="ulid", description="ID de la ville de la mission"),
 *     @OA\Property(property="date_generation", type="string", format="date-time", description="Date de génération de l'ordre de mission"),
 *     @OA\Property(property="date_debut_candidature", type="string", format="date-time", nullable=true, description="Date de début des candidatures"),
 *     @OA\Property(property="date_fin_candidature", type="string", format="date-time", nullable=true, description="Date de fin des candidatures"),
 *     @OA\Property(property="nombre_techniciens_requis", type="integer", description="Nombre de techniciens requis"),
 *     @OA\Property(property="nombre_techniciens_acceptes", type="integer", description="Nombre de techniciens acceptés"),
 *     @OA\Property(property="candidature_cloturee", type="boolean", description="Indique si les candidatures sont clôturées manuellement"),
 *     @OA\Property(property="date_cloture_candidature", type="string", format="date-time", nullable=true, description="Date de clôture des candidatures"),
 *     @OA\Property(property="cloture_par", type="string", format="ulid", nullable=true, description="ID de l'utilisateur ayant clôturé les candidatures"),
 *     @OA\Property(property="valide_par", type="string", format="ulid", nullable=true, description="ID de l'utilisateur ayant validé l'ordre de mission"),
 *     @OA\Property(property="statut", type="string", enum={"en_attente", "en_cours", "termine", "cloture"}, description="Statut de l'ordre de mission"),
 *     @OA\Property(property="commentaire", type="string", nullable=true, description="Commentaire sur l'ordre de mission"),
 *     @OA\Property(property="numero_ordre", type="string", description="Numéro unique de l'ordre de mission"),
 *     @OA\Property(property="panne", ref="#/components/schemas/Panne", description="Détails de la panne associée"),
 *     @OA\Property(property="ville", ref="#/components/schemas/Ville", description="Détails de la ville associée"),
 *     @OA\Property(property="validePar", ref="#/components/schemas/User", description="Utilisateur ayant validé l'ordre de mission"),
 *     @OA\Property(property="cloturePar", ref="#/components/schemas/User", description="Utilisateur ayant clôturé les candidatures"),
 *     @OA\Property(property="interventions", type="array", @OA\Items(ref="#/components/schemas/Intervention"), description="Liste des interventions liées à cet ordre de mission"),
 *     @OA\Property(property="missionsTechniciens", type="array", @OA\Items(ref="#/components/schemas/MissionTechnicien"), description="Liste des candidatures de techniciens pour cet ordre de mission")
 * )
 *
 * @OA\Schema(
 *     schema="Panne",
 *     title="Panne",
 *     description="Détails d'une panne",
 *     @OA\Property(property="id", type="string", format="ulid", description="ID de la panne"),
 *     @OA\Property(property="ecole_id", type="string", format="ulid", description="ID de l'école associée"),
 *     @OA\Property(property="sirene_id", type="string", format="ulid", description="ID de la sirène associée"),
 *     @OA\Property(property="site_id", type="string", format="ulid", description="ID du site associé"),
 *     @OA\Property(property="numero_panne", type="string", description="Numéro unique de la panne"),
 *     @OA\Property(property="description", type="string", description="Description de la panne"),
 *     @OA\Property(property="date_signalement", type="string", format="date-time", description="Date de signalement de la panne"),
 *     @OA\Property(property="priorite", type="string", enum={"basse", "moyenne", "haute", "urgente"}, description="Priorité de la panne"),
 *     @OA\Property(property="statut", type="string", enum={"signalee", "en_attente_validation", "validee", "en_cours_traitement", "resolue", "annulee"}, description="Statut de la panne"),
 *     @OA\Property(property="date_declaration", type="string", format="date-time", nullable=true, description="Date de déclaration de la panne"),
 *     @OA\Property(property="date_validation", type="string", format="date-time", nullable=true, description="Date de validation de la panne"),
 *     @OA\Property(property="valide_par", type="string", format="ulid", nullable=true, description="ID de l'utilisateur ayant validé la panne"),
 *     @OA\Property(property="est_cloture", type="boolean", description="Indique si la panne est clôturée"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="sirene", ref="#/components/schemas/Sirene", description="Détails de la sirène associée"),
 * )
 *

 *
 * @OA\Schema(
 *     schema="Ville",
 *     title="Ville",
 *     description="Détails d'une ville",
 *     @OA\Property(property="id", type="string", format="ulid", description="ID de la ville"),
 *     @OA\Property(property="pays_id", type="string", format="ulid", description="ID du pays associé"),
 *     @OA\Property(property="nom", type="string", description="Nom de la ville"),
 *     @OA\Property(property="code", type="string", nullable=true, description="Code de la ville"),
 *     @OA\Property(property="latitude", type="number", format="float", nullable=true, description="Latitude de la ville"),
 *     @OA\Property(property="longitude", type="number", format="float", nullable=true, description="Longitude de la ville"),
 *     @OA\Property(property="actif", type="boolean", description="Indique si la ville est active"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 * )
 *
 * @OA\Schema(
 *     schema="User",
 *     title="User",
 *     description="Détails d'un utilisateur",
 *     @OA\Property(property="id", type="string", format="ulid", description="ID de l'utilisateur"),
 *     @OA\Property(property="nom_utilisateur", type="string", description="Nom d'utilisateur"),
 *     @OA\Property(property="identifiant", type="string", description="Identifiant de l'utilisateur"),
 *     @OA\Property(property="type", type="string", description="Type de compte utilisateur"),
 *     @OA\Property(property="role_id", type="string", format="ulid", description="ID du rôle de l'utilisateur"),
 *     @OA\Property(property="actif", type="boolean", description="Indique si l'utilisateur est actif"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 * )
 *
 * @OA\Schema(
 *     schema="MissionTechnicien",
 *     title="Mission Technicien",
 *     description="Détails d'une mission de technicien (candidature)",
 *     @OA\Property(property="id", type="string", format="ulid", description="ID de la mission"),
 *     @OA\Property(property="ordre_mission_id", type="string", format="ulid", description="ID de l'ordre de mission"),
 *     @OA\Property(property="technicien_id", type="string", format="ulid", description="ID du technicien"),
 *     @OA\Property(property="statut_candidature", type="string", enum={"en_attente", "acceptee", "refusee", "retiree"}, description="Statut de la candidature"),
 *     @OA\Property(property="statut", type="string", enum={"non_demarree", "en_cours", "terminee", "annulee"}, description="Statut de la mission"),
 *     @OA\Property(property="date_acceptation", type="string", format="date-time", nullable=true, description="Date d'acceptation de la candidature"),
 *     @OA\Property(property="date_cloture", type="string", format="date-time", nullable=true, description="Date de clôture de la mission"),
 *     @OA\Property(property="date_retrait", type="string", format="date-time", nullable=true, description="Date de retrait de la candidature"),
 *     @OA\Property(property="motif_retrait", type="string", nullable=true, description="Motif du retrait de la candidature"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="technicien", ref="#/components/schemas/Technicien", description="Détails du technicien"),
 * )
 *
 * @OA\Schema(
 *     schema="Intervention",
 *     title="Intervention",
 *     description="Détails d'une intervention",
 *     @OA\Property(property="id", type="string", format="ulid", description="ID de l'intervention"),
 *     @OA\Property(property="panne_id", type="string", format="ulid", description="ID de la panne associée"),
 *     @OA\Property(property="technicien_id", type="string", format="ulid", description="ID du technicien assigné"),
 *     @OA\Property(property="ordre_mission_id", type="string", format="ulid", description="ID de l'ordre de mission associé"),
 *     @OA\Property(property="date_intervention", type="string", format="date-time", nullable=true, description="Date planifiée de l'intervention"),
 *     @OA\Property(property="date_affectation", type="string", format="date-time", nullable=true, description="Date d'affectation du technicien"),
 *     @OA\Property(property="date_assignation", type="string", format="date-time", nullable=true, description="Date d'assignation de la mission"),
 *     @OA\Property(property="date_acceptation", type="string", format="date-time", nullable=true, description="Date d'acceptation par le technicien"),
 *     @OA\Property(property="date_debut", type="string", format="date-time", nullable=true, description="Date de début de l'intervention"),
 *     @OA\Property(property="date_fin", type="string", format="date-time", nullable=true, description="Date de fin de l'intervention"),
 *     @OA\Property(property="statut", type="string", enum={"planifiee", "en_cours", "terminee", "annulee", "reportee"}, description="Statut de l'intervention"),
 *     @OA\Property(property="note_ecole", type="integer", nullable=true, description="Note attribuée par l'école"),
 *     @OA\Property(property="commentaire_ecole", type="string", nullable=true, description="Commentaire de l'école"),
 *     @OA\Property(property="observations", type="string", nullable=true, description="Observations du technicien"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="technicien", ref="#/components/schemas/Technicien", description="Détails du technicien"),
 * )
 *

 */
class OrdreMissionController extends Controller
{
    protected OrdreMissionServiceInterface $ordreMissionService;

    public function __construct(OrdreMissionServiceInterface $ordreMissionService)
    {
        $this->ordreMissionService = $ordreMissionService;
    }

    /**
     * Lister tous les ordres de mission
     *
     * @OA\Get(
     *     path="/api/ordres-mission",
     *     tags={"Pannes & Interventions"},
     *     summary="Liste de tous les ordres de mission",
     *     description="Récupère la liste paginée de tous les ordres de mission avec leurs pannes, villes et interventions",
     *     operationId="getAllOrdresMission",
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
     *         description="Liste des ordres de mission récupérée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/OrdreMission")),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="total", type="integer", example=50)
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('voir_les_ordres_mission');
        $perPage = $request->get('per_page', 15);
        return $this->ordreMissionService->getAll($perPage, ['panne', 'ville', 'validePar', 'interventions.technicien']);
    }

    /**
     * Afficher les détails d'un ordre de mission
     *
     * @OA\Get(
     *     path="/api/ordres-mission/{id}",
     *     tags={"Pannes & Interventions"},
     *     summary="Détails d'un ordre de mission",
     *     description="Récupère les détails complets d'un ordre de mission avec la panne, la sirène, les candidatures et interventions",
     *     operationId="getOrdreMissionDetails",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de l'ordre de mission",
     *         @OA\Schema(type="string", example="01ARZ3NDEKTSV4RRFFQ69G5FAV")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Détails de l'ordre de mission récupérés avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/OrdreMission")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Ordre de mission non trouvé"
     *     )
     * )
     */
    public function show(string $id): JsonResponse
    {
        Gate::authorize('voir_ordre_mission');
        return $this->ordreMissionService->getById($id, ['panne.sirene', 'ville', 'validePar', 'interventions.technicien', 'missionsTechniciens.technicien']);
    }

    /**
     * Créer un ordre de mission
     *
     * @OA\Post(
     *     path="/api/ordres-mission",
     *     tags={"Pannes & Interventions"},
     *     summary="Créer un ordre de mission",
     *     description="Crée un nouvel ordre de mission pour une panne validée",
     *     operationId="createOrdreMission",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Informations de l'ordre de mission",
     *         @OA\JsonContent(
     *             required={"panne_id", "ville_id", "valide_par"},
     *             @OA\Property(property="panne_id", type="string", example="01ARZ3NDEKTSV4RRFFQ69G5FAV", description="ID de la panne"),
     *             @OA\Property(property="ville_id", type="string", example="01ARZ3NDEKTSV4RRFFQ69G5FAV", description="ID de la ville"),
     *             @OA\Property(property="valide_par", type="string", example="01ARZ3NDEKTSV4RRFFQ69G5FAV", description="ID de l'administrateur validant"),
     *             @OA\Property(property="date_debut_candidature", type="string", format="date", example="2025-11-10", description="Date d'ouverture des candidatures"),
     *             @OA\Property(property="date_fin_candidature", type="string", format="date", example="2025-11-15", description="Date de fermeture des candidatures"),
     *             @OA\Property(property="nombre_techniciens_requis", type="integer", example=2, description="Nombre de techniciens nécessaires"),
     *             @OA\Property(property="commentaire", type="string", example="Intervention urgente", description="Commentaire")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Ordre de mission créé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Ordre de mission créé avec succès"),
     *             @OA\Property(property="data", ref="#/components/schemas/OrdreMission")
     *         )
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('creer_ordre_mission');
        $validated = $request->validate([
            'panne_id' => 'required|string|exists:pannes,id',
            'ville_id' => 'required|string|exists:villes,id',
            'valide_par' => 'required|string|exists:users,id',
            'date_debut_candidature' => 'nullable|date',
            'date_fin_candidature' => 'nullable|date|after:date_debut_candidature',
            'nombre_techniciens_requis' => 'nullable|integer|min:1',
            'commentaire' => 'nullable|string',
        ]);

        return $this->ordreMissionService->create($validated);
    }

    /**
     * Récupérer les candidatures d'un ordre de mission
     *
     * @OA\Get(
     *     path="/api/ordres-mission/{id}/candidatures",
     *     tags={"Pannes & Interventions"},
     *     summary="Candidatures d'un ordre de mission",
     *     description="Récupère toutes les candidatures soumises par les techniciens pour un ordre de mission",
     *     operationId="getCandidaturesOrdreMission",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de l'ordre de mission",
     *         @OA\Schema(type="string", example="01ARZ3NDEKTSV4RRFFQ69G5FAV")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des candidatures récupérée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/MissionTechnicien"))
     *         )
     *     )
     * )
     */
    public function getCandidatures(string $ordreMissionId): JsonResponse
    {
        Gate::authorize('voir_les_missions_technicien');
        return $this->ordreMissionService->getCandidaturesByOrdreMission($ordreMissionId);
    }

    /**
     * Récupérer les ordres de mission par ville
     *
     * @OA\Get(
     *     path="/api/ordres-mission/ville/{villeId}",
     *     tags={"Pannes & Interventions"},
     *     summary="Ordres de mission d'une ville",
     *     description="Récupère tous les ordres de mission pour une ville spécifique",
     *     operationId="getOrdreMissionsByVille",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="villeId",
     *         in="path",
     *         required=true,
     *         description="ID de la ville",
     *         @OA\Schema(type="string", example="01ARZ3NDEKTSV4RRFFQ69G5FAV")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des ordres de mission de la ville",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/OrdreMission"))
     *         )
     *     )
     * )
     */
    public function getByVille(string $villeId): JsonResponse
    {
        Gate::authorize('voir_les_ordres_mission');
        return $this->ordreMissionService->getOrdreMissionsByVille($villeId);
    }

    /**
     * Mettre à jour un ordre de mission
     *
     * @OA\Put(
     *     path="/api/ordres-mission/{id}",
     *     tags={"Pannes & Interventions"},
     *     summary="Mettre à jour un ordre de mission",
     *     description="Met à jour les informations d'un ordre de mission existant",
     *     operationId="updateOrdreMission",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de l'ordre de mission",
     *         @OA\Schema(type="string", example="01ARZ3NDEKTSV4RRFFQ69G5FAV")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         description="Champs à mettre à jour",
     *         @OA\JsonContent(
     *             @OA\Property(property="statut", type="string", enum={"en_attente", "en_cours", "termine", "cloture"}, example="en_cours"),
     *             @OA\Property(property="date_debut_candidature", type="string", format="date"),
     *             @OA\Property(property="date_fin_candidature", type="string", format="date"),
     *             @OA\Property(property="nombre_techniciens_requis", type="integer"),
     *             @OA\Property(property="commentaire", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Ordre de mission mis à jour avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Ordre de mission mis à jour"),
     *             @OA\Property(property="data", ref="#/components/schemas/OrdreMission")
     *         )
     *     )
     * )
     */
    public function update(Request $request, string $id): JsonResponse
    {
        Gate::authorize('modifier_ordre_mission');
        $validated = $request->validate([
            'statut' => 'sometimes|string|in:en_attente,en_cours,termine,cloture',
            'date_debut_candidature' => 'nullable|date',
            'date_fin_candidature' => 'nullable|date|after:date_debut_candidature',
            'nombre_techniciens_requis' => 'nullable|integer|min:1',
            'commentaire' => 'nullable|string',
        ]);

        return $this->ordreMissionService->update($id, $validated);
    }

    /**
     * Supprimer un ordre de mission
     *
     * @OA\Delete(
     *     path="/api/ordres-mission/{id}",
     *     tags={"Pannes & Interventions"},
     *     summary="Supprimer un ordre de mission",
     *     description="Supprime un ordre de mission (administrateur uniquement)",
     *     operationId="deleteOrdreMission",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de l'ordre de mission",
     *         @OA\Schema(type="string", example="01ARZ3NDEKTSV4RRFFQ69G5FAV")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Ordre de mission supprimé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Ordre de mission supprimé avec succès")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Ordre de mission non trouvé"
     *     )
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        Gate::authorize('supprimer_ordre_mission');
        return $this->ordreMissionService->delete($id);
    }

    /**
     * Clôturer les candidatures d'un ordre de mission
     *
     * @OA\Put(
     *     path="/api/ordres-mission/{id}/cloturer-candidatures",
     *     tags={"Pannes & Interventions"},
     *     summary="Clôturer les candidatures",
     *     description="Ferme les candidatures pour un ordre de mission. Aucun nouveau technicien ne pourra postuler après cette action.",
     *     operationId="cloturerCandidatures",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de l'ordre de mission",
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
     *         description="Candidatures clôturées avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Candidatures clôturées avec succès")
     *         )
     *     )
     * )
     */
    public function cloturerCandidatures(Request $request, string $id): JsonResponse
    {
        Gate::authorize('modifier_ordre_mission');
        $validated = $request->validate([
            'admin_id' => 'required|string|exists:users,id',
        ]);

        return $this->ordreMissionService->cloturerCandidatures($id, $validated['admin_id']);
    }

    /**
     * Rouvrir les candidatures d'un ordre de mission
     *
     * @OA\Put(
     *     path="/api/ordres-mission/{id}/rouvrir-candidatures",
     *     tags={"Pannes & Interventions"},
     *     summary="Rouvrir les candidatures",
     *     description="Réouvre les candidatures pour un ordre de mission. Les techniciens pourront à nouveau postuler.",
     *     operationId="rouvrirCandidatures",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de l'ordre de mission",
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
     *         description="Candidatures rouvertes avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Candidatures rouvertes avec succès")
     *         )
     *     )
     * )
     */
    public function rouvrirCandidatures(Request $request, string $id): JsonResponse
    {
        Gate::authorize('modifier_ordre_mission');
        $validated = $request->validate([
            'admin_id' => 'required|string|exists:users,id',
        ]);

        return $this->ordreMissionService->rouvrirCandidatures($id, $validated['admin_id']);
    }
}
