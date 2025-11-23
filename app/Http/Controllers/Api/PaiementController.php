<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Contracts\PaiementServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class PaiementController extends Controller
{
    protected PaiementServiceInterface $paiementService;

    public function __construct(PaiementServiceInterface $paiementService)
    {
        $this->paiementService = $paiementService;
        // Middleware appliqué au niveau des routes
    }

    /**
     * Traiter un paiement pour un abonnement
     *
     * @OA\Post(
     *     path="/api/paiements/abonnements/{abonnementId}",
     *     tags={"Paiements"},
     *     summary="Traiter un paiement pour un abonnement",
     *     description="Crée et traite un paiement pour un abonnement. Peut être appelé via QR code.",
     *     operationId="traiterPaiement",
     *     @OA\Parameter(
     *         name="abonnementId",
     *         in="path",
     *         required=true,
     *         description="ID de l'abonnement",
     *         @OA\Schema(type="string", example="01ARZ3NDEKTSV4RRFFQ69G5FAV")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Informations du paiement",
     *         @OA\JsonContent(
     *             required={"montant", "moyen"},
     *             @OA\Property(property="montant", type="number", example=50000, description="Montant du paiement"),
     *             @OA\Property(property="moyen", type="string", enum={"MOBILE_MONEY", "CARTE_BANCAIRE", "QR_CODE", "VIREMENT"}, example="MOBILE_MONEY", description="Moyen de paiement utilisé"),
     *             @OA\Property(property="reference_externe", type="string", example="CPAY-123456", description="Référence externe du paiement (ex: CinetPay transaction ID)"),
     *             @OA\Property(property="metadata", type="object", description="Métadonnées additionnelles")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Paiement traité avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Paiement traité avec succès"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string", example="01ARZ3NDEKTSV4RRFFQ69G5FAV"),
     *                 @OA\Property(property="montant", type="number", example=50000),
     *                 @OA\Property(property="statut", type="string", example="valide"),
     *                 @OA\Property(property="moyen", type="string", example="MOBILE_MONEY")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Données invalides"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Abonnement non trouvé"
     *     )
     * )
     */
    public function traiter(Request $request, string $ecoleId, string $abonnementId = null): JsonResponse
    {
        Gate::authorize('creer_paiement');
        $validated = $request->validate([
            'montant' => 'required|numeric|min:0',
            'moyen' => 'required|in:MOBILE_MONEY,CARTE_BANCAIRE,QR_CODE,VIREMENT',
            'reference_externe' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        if($abonnementId == null) $abonnementId = $ecoleId;

        return $this->paiementService->traiterPaiement($abonnementId, $validated);
    }

    /**
     * Valider un paiement (webhook ou admin)
     *
     * @OA\Put(
     *     path="/api/paiements/{id}/valider",
     *     tags={"Paiements"},
     *     summary="Valider un paiement",
     *     description="Valide un paiement en attente. Peut être appelé par un webhook ou un administrateur.",
     *     operationId="validerPaiement",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID du paiement à valider",
     *         @OA\Schema(type="string", example="01ARZ3NDEKTSV4RRFFQ69G5FAV")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Paiement validé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Paiement validé avec succès"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string"),
     *                 @OA\Property(property="statut", type="string", example="valide")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Paiement non trouvé"
     *     )
     * )
     */
    public function valider(string $paiementId): JsonResponse
    {
        Gate::authorize('modifier_paiement');
        return $this->paiementService->validerPaiement($paiementId);
    }

    /**
     * Lister les paiements d'un abonnement
     *
     * @OA\Get(
     *     path="/api/paiements/abonnements/{abonnementId}",
     *     tags={"Paiements"},
     *     summary="Liste des paiements d'un abonnement",
     *     description="Récupère tous les paiements associés à un abonnement",
     *     operationId="getPaiementsByAbonnement",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="abonnementId",
     *         in="path",
     *         required=true,
     *         description="ID de l'abonnement",
     *         @OA\Schema(type="string", example="01ARZ3NDEKTSV4RRFFQ69G5FAV")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des paiements récupérée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="string"),
     *                     @OA\Property(property="montant", type="number", example=50000),
     *                     @OA\Property(property="moyen", type="string", example="MOBILE_MONEY"),
     *                     @OA\Property(property="statut", type="string", example="valide"),
     *                     @OA\Property(property="date_paiement", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Abonnement non trouvé"
     *     )
     * )
     */
    public function parAbonnement(string $abonnementId): JsonResponse
    {
        Gate::authorize('voir_les_paiements');
        return $this->paiementService->getPaiementsByAbonnement($abonnementId);
    }

    /**
     * Afficher les détails d'un paiement
     *
     * @OA\Get(
     *     path="/api/paiements/{id}",
     *     tags={"Paiements"},
     *     summary="Détails d'un paiement",
     *     description="Récupère les détails complets d'un paiement avec les informations de l'abonnement et de l'école",
     *     operationId="getPaiementDetails",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID du paiement",
     *         @OA\Schema(type="string", example="01ARZ3NDEKTSV4RRFFQ69G5FAV")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Détails du paiement récupérés avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string"),
     *                 @OA\Property(property="montant", type="number", example=50000),
     *                 @OA\Property(property="moyen", type="string", example="MOBILE_MONEY"),
     *                 @OA\Property(property="statut", type="string", example="valide"),
     *                 @OA\Property(property="reference_externe", type="string", example="CPAY-123456"),
     *                 @OA\Property(property="abonnement", type="object",
     *                     @OA\Property(property="id", type="string"),
     *                     @OA\Property(property="ecole", type="object",
     *                         @OA\Property(property="id", type="string"),
     *                         @OA\Property(property="nom", type="string")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Paiement non trouvé"
     *     )
     * )
     */
    public function show(string $id): JsonResponse
    {
        Gate::authorize('voir_paiement');
        return $this->paiementService->getById($id, relations: ['abonnement.ecole']);
    }

    /**
     * Lister tous les paiements (admin)
     *
     * @OA\Get(
     *     path="/api/paiements",
     *     tags={"Paiements"},
     *     summary="Liste de tous les paiements",
     *     description="Récupère la liste paginée de tous les paiements (accès administrateur)",
     *     operationId="getAllPaiements",
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
     *         description="Liste des paiements récupérée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="data", type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="string"),
     *                         @OA\Property(property="montant", type="number", example=50000),
     *                         @OA\Property(property="moyen", type="string", example="MOBILE_MONEY"),
     *                         @OA\Property(property="statut", type="string", example="valide"),
     *                         @OA\Property(property="abonnement", type="object"),
     *                         @OA\Property(property="ecole", type="object")
     *                     )
     *                 ),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="total", type="integer", example=100)
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('voir_les_paiements');
        $perPage = $request->get('per_page', 15);
        return $this->paiementService->getAll($perPage, ['abonnement', 'ecole']);
    }
}
