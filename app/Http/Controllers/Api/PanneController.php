<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sirene;
use App\Services\Contracts\PanneServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use OpenApi\Annotations as OA;

class PanneController extends Controller
{
    protected PanneServiceInterface $panneService;

    public function __construct(PanneServiceInterface $panneService)
    {
        $this->panneService = $panneService;
    }

    /**
     * Liste de toutes les pannes
     *
     * @OA\Get(
     *     path="/api/pannes",
     *     tags={"Pannes & Interventions"},
     *     summary="Liste de toutes les pannes",
     *     description="Récupère la liste paginée de toutes les pannes",
     *     operationId="getAllPannes",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Nombre de résultats par page",
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des pannes récupérée avec succès"
     *     )
     * )
     */
    public function index(Request $request)
    {
        Gate::authorize('voir_les_pannes');
        $perPage = $request->get('per_page', 15);
        return $this->panneService->getAll($perPage, ['sirene', 'site', 'ordreMission', 'interventions']);
    }

    /**
     * Détails d'une panne
     *
     * @OA\Get(
     *     path="/api/pannes/{id}",
     *     tags={"Pannes & Interventions"},
     *     summary="Détails d'une panne",
     *     description="Récupère les détails complets d'une panne",
     *     operationId="getPanneDetails",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la panne",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Détails de la panne récupérés avec succès"
     *     )
     * )
     */
    public function show(string $id)
    {
        Gate::authorize('voir_panne');
        return $this->panneService->getById($id, ['sirene', 'site', 'ordreMission', 'interventions.ordreMission']);
    }

    /**
     * Déclarer une panne pour une sirène
     *
     * @OA\Post(
     *     path="/api/sirenes/{id}/declarer-panne",
     *     tags={"Pannes & Interventions"},
     *     summary="Déclarer une panne",
     *     description="Permet de déclarer une panne sur une sirène. L'école ou un technicien peut signaler un dysfonctionnement.",
     *     operationId="declarerPanne",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la sirène",
     *         @OA\Schema(type="string", example="01ARZ3NDEKTSV4RRFFQ69G5FAV")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Informations de la panne",
     *         @OA\JsonContent(
     *             required={"description"},
     *             @OA\Property(property="description", type="string", example="La sirène ne sonne plus depuis ce matin", description="Description détaillée de la panne"),
     *             @OA\Property(property="priorite", type="string", enum={"faible", "moyenne", "haute"}, example="moyenne", description="Niveau de priorité de la panne")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Panne déclarée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="string"),
     *             @OA\Property(property="sirene_id", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="priorite", type="string", example="moyenne"),
     *             @OA\Property(property="statut", type="string", example="en_attente"),
     *             @OA\Property(property="created_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Sirène non trouvée"
     *     )
     * )
     */
    public function declarer(Request $request, $sireneId)
    {
        Gate::authorize('creer_panne');
        $sirene = Sirene::findOrFail($sireneId);

        $validated = $request->validate([
            'description' => 'required|string',
            'priorite' => 'sometimes|string|in:faible,moyenne,haute',
        ]);

        $panne = $sirene->declarerPanne($validated['description'], $validated['priorite'] ?? 'moyenne');

        return response()->json($panne, 201);
    }

    /**
     * Valider une panne et créer un ordre de mission
     *
     * @OA\Put(
     *     path="/api/pannes/{panneId}/valider",
     *     tags={"Pannes & Interventions"},
     *     summary="Valider une panne",
     *     description="Valide une panne déclarée et crée automatiquement un ordre de mission pour les techniciens. Accessible aux administrateurs.",
     *     operationId="validerPanne",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="panneId",
     *         in="path",
     *         required=true,
     *         description="ID de la panne à valider",
     *         @OA\Schema(type="string", example="01ARZ3NDEKTSV4RRFFQ69G5FAV")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Paramètres de validation et de l'ordre de mission",
     *         @OA\JsonContent(
     *             required={"nombre_techniciens_requis"},
     *             @OA\Property(property="nombre_techniciens_requis", type="integer", minimum=1, example=2, description="Nombre de techniciens nécessaires (OBLIGATOIRE)"),
     *             @OA\Property(property="date_debut_candidature", type="string", format="date", example="2025-11-10", description="Date d'ouverture des candidatures"),
     *             @OA\Property(property="date_fin_candidature", type="string", format="date", example="2025-11-15", description="Date de fermeture des candidatures"),
     *             @OA\Property(property="commentaire", type="string", example="Intervention urgente requise", description="Commentaire de l'administrateur")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Panne validée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Panne validée et ordre de mission créé"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="panne", type="object"),
     *                 @OA\Property(property="ordre_mission", type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Panne non trouvée"
     *     )
     * )
     */
    public function valider(Request $request, $panneId)
    {
        Gate::authorize('modifier_panne');
        $validated = $request->validate([
            'nombre_techniciens_requis' => 'required|integer|min:1', // OBLIGATOIRE
            'date_debut_candidature' => 'nullable|date',
            'date_fin_candidature' => 'nullable|date|after:date_debut_candidature',
            'commentaire' => 'nullable|string',
        ]);

        return $this->panneService->validerPanne($panneId, $validated);
    }

    /**
     * Clôturer une panne
     *
     * @OA\Put(
     *     path="/api/pannes/{panneId}/cloturer",
     *     tags={"Pannes & Interventions"},
     *     summary="Clôturer une panne",
     *     description="Marque une panne comme résolue et clôturée. Cette action termine le cycle de vie de la panne.",
     *     operationId="cloturerPanne",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="panneId",
     *         in="path",
     *         required=true,
     *         description="ID de la panne à clôturer",
     *         @OA\Schema(type="string", example="01ARZ3NDEKTSV4RRFFQ69G5FAV")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Panne clôturée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Panne clôturée avec succès"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string"),
     *                 @OA\Property(property="statut", type="string", example="cloture")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Panne non trouvée"
     *     )
     * )
     */
    public function cloturer($panneId)
    {
        Gate::authorize('resoudre_panne');
        return $this->panneService->cloturerPanne($panneId);
    }

    /**
     * Assigner un technicien à une panne
     *
     * @OA\Put(
     *     path="/api/pannes/{panneId}/assigner/{technicienId}",
     *     tags={"Pannes & Interventions"},
     *     summary="Assigner un technicien à une panne",
     *     description="Assigne un technicien à l'intervention liée à la panne",
     *     operationId="assignerTechnicien",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="panneId",
     *         in="path",
     *         required=true,
     *         description="ID de la panne",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="technicienId",
     *         in="path",
     *         required=true,
     *         description="ID du technicien",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Technicien assigné avec succès"
     *     )
     * )
     */
    public function assignerTechnicien(string $panneId, string $technicienId)
    {
        Gate::authorize('assigner_technicien_intervention');
        return $this->panneService->assignerTechnicien($panneId, $technicienId);
    }

    /**
     * Mettre à jour le statut d'une panne
     *
     * @OA\Put(
     *     path="/api/pannes/{panneId}",
     *     tags={"Pannes & Interventions"},
     *     summary="Mettre à jour une panne",
     *     description="Met à jour le statut ou d'autres informations d'une panne",
     *     operationId="updatePanne",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="panneId",
     *         in="path",
     *         required=true,
     *         description="ID de la panne",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="statut", type="string", example="en_cours")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Panne mise à jour avec succès"
     *     )
     * )
     */
    public function update(Request $request, string $panneId)
    {
        Gate::authorize('modifier_panne');
        $validated = $request->validate([
            'statut' => 'required|string',
        ]);

        return $this->panneService->updateStatut($panneId, $validated['statut']);
    }

    /**
     * Récupérer les pannes d'une sirène
     *
     * @OA\Get(
     *     path="/api/sirenes/{sireneId}/pannes",
     *     tags={"Pannes & Interventions"},
     *     summary="Pannes d'une sirène",
     *     description="Récupère toutes les pannes déclarées pour une sirène",
     *     operationId="getPannesBySirene",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="sireneId",
     *         in="path",
     *         required=true,
     *         description="ID de la sirène",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Pannes récupérées avec succès"
     *     )
     * )
     */
    public function pannesBySirene(string $sireneId)
    {
        Gate::authorize('voir_les_pannes');
        return $this->panneService->getPannesBySirene($sireneId);
    }

    /**
     * Récupérer les pannes d'une école
     *
     * @OA\Get(
     *     path="/api/ecoles/{ecoleId}/pannes",
     *     tags={"Pannes & Interventions"},
     *     summary="Pannes d'une école",
     *     description="Récupère toutes les pannes déclarées par une école",
     *     operationId="getPannesByEcole",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="ecoleId",
     *         in="path",
     *         required=true,
     *         description="ID de l'école",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Pannes récupérées avec succès"
     *     )
     * )
     */
    public function pannesByEcole(string $ecoleId)
    {
        Gate::authorize('voir_les_pannes');
        return $this->panneService->getPannesByEcole($ecoleId);
    }

    /**
     * Récupérer les statistiques des pannes
     *
     * @OA\Get(
     *     path="/api/statistiques-pannes",
     *     tags={"Pannes & Interventions"},
     *     summary="Statistiques des pannes",
     *     description="Récupère les statistiques globales des pannes (total, par statut, par priorité, etc.)",
     *     operationId="getStatistiquesPannes",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Statistiques récupérées avec succès"
     *     )
     * )
     */
    public function statistiques()
    {
        Gate::authorize('voir_les_pannes');
        return $this->panneService->getStatistiques();
    }
}
