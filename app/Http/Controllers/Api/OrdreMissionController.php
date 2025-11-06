<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Contracts\OrdreMissionServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

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
     *                 @OA\Property(property="data", type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="string"),
     *                         @OA\Property(property="panne", type="object"),
     *                         @OA\Property(property="ville", type="object"),
     *                         @OA\Property(property="statut", type="string", example="en_attente"),
     *                         @OA\Property(property="interventions", type="array", @OA\Items(type="object"))
     *                     )
     *                 ),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="total", type="integer", example=50)
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
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
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string"),
     *                 @OA\Property(property="panne", type="object",
     *                     @OA\Property(property="sirene", type="object")
     *                 ),
     *                 @OA\Property(property="ville", type="object"),
     *                 @OA\Property(property="statut", type="string"),
     *                 @OA\Property(property="interventions", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="missionsTechniciens", type="array", @OA\Items(type="object"), description="Candidatures des techniciens")
     *             )
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
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
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
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="string"),
     *                     @OA\Property(property="technicien", type="object"),
     *                     @OA\Property(property="statut", type="string", example="en_attente"),
     *                     @OA\Property(property="date_candidature", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getCandidatures(string $ordreMissionId): JsonResponse
    {
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
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function getByVille(string $villeId): JsonResponse
    {
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
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function update(Request $request, string $id): JsonResponse
    {
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
        $validated = $request->validate([
            'admin_id' => 'required|string|exists:users,id',
        ]);

        return $this->ordreMissionService->rouvrirCandidatures($id, $validated['admin_id']);
    }
}
