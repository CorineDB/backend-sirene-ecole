<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sirene\AffecterSireneRequest;
use App\Http\Requests\Sirene\CreateSireneRequest;
use App\Http\Requests\Sirene\UpdateSireneRequest;
use App\Services\Contracts\SireneServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use OpenApi\Annotations as OA;

/**
 * Class SireneController
 * @package App\Http\Controllers\Api
 * @OA\Tag(
 *     name="Sirenes",
 *     description="API Endpoints for Sirene Management"
 * )
 * @OA\Schema(
 *     schema="Sirene",
 *     title="Sirene",
 *     description="Sirene model",
 *     @OA\Property(property="id", type="string", format="uuid", description="ID of the sirene"),
 *     @OA\Property(property="numero_serie", type="string", description="Serial number of the sirene"),
 *     @OA\Property(property="modele_id", type="string", format="uuid", description="ID of the sirene model"),
 *     @OA\Property(property="date_fabrication", type="string", format="date", description="Manufacturing date"),
 *     @OA\Property(property="etat", type="string", description="State of the sirene (e.g., NEUF, BON)"),
 *     @OA\Property(property="statut", type="string", description="Status of the sirene"),
 *     @OA\Property(property="notes", type="string", nullable=true, description="Additional notes"),
 *     @OA\Property(property="ecole_id", type="string", format="uuid", nullable=true, description="ID of the associated school"),
 *     @OA\Property(property="site_id", type="string", format="uuid", nullable=true, description="ID of the associated site")
 * )
 */
class SireneController extends Controller
{
    protected $sireneService;

    public function __construct(SireneServiceInterface $sireneService)
    {
        $this->sireneService = $sireneService;
    }

    public static function middleware(): array
    {
        return [
            // Les middlewares sont appliqués dans les routes
            // On les laisse commentés ici pour éviter la duplication
            /*new Middleware('can:voir_les_sirenes', only: ['index', 'disponibles']),
            new Middleware('can:creer_sirene', only: ['store']),
            new Middleware('can:voir_sirene', only: ['show', 'showByNumeroSerie']),
            new Middleware('can:modifier_sirene', only: ['update', 'affecter']),
            new Middleware('can:supprimer_sirene', only: ['destroy']),*/
        ];
    }

    /**
     * Lister toutes les sirènes
     * @OA\Get(
     *     path="/api/sirenes",
     *     summary="List all sirenes",
     *     tags={"Sirenes"},
     *     security={ {"passport": {}} },
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of sirenes per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Sirene"))
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('voir_les_sirenes');
        $perPage = $request->get('per_page', 15);
        return $this->sireneService->getAll(1000, relations:['modeleSirene', 'ecole', 'site']);
    }

    /**
     * Créer une nouvelle sirène (Admin seulement - génération à l'usine)
     * @OA\Post(
     *     path="/api/sirenes",
     *     summary="Create a new sirene (Admin only)",
     *     tags={"Sirenes"},
     *     security={ {"passport": {}} },
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/CreateSireneRequest")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Sirene created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Sirene")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="This action is unauthorized.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid.")
     *         )
     *     )
     * )
     */
    public function store(CreateSireneRequest $request): JsonResponse
    {
        Gate::authorize('creer_sirene');
        return $this->sireneService->create($request->validated());
    }

    /**
     * Afficher les détails d'une sirène
     * @OA\Get(
     *     path="/api/sirenes/{id}",
     *     summary="Get sirene details by ID",
     *     tags={"Sirenes"},
     *     security={ {"passport": {}} },
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the sirene to retrieve",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Sirene")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Sirene not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Sirene not found")
     *         )
     *     )
     * )
     */
    public function show(string $id): JsonResponse
    {
        Gate::authorize('voir_sirene');
        return $this->sireneService->getById($id, relations:[
            'modeleSirene',
            'ecole',
            'site.ecolePrincipale',
            'abonnements'
        ]);
    }

    /**
     * Rechercher une sirène par numéro de série
     * @OA\Get(
     *     path="/api/sirenes/numero-serie/{numeroSerie}",
     *     summary="Get sirene details by serial number",
     *     tags={"Sirenes"},
     *     security={ {"passport": {}} },
     *     @OA\Parameter(
     *         name="numeroSerie",
     *         in="path",
     *         required=true,
     *         description="Serial number of the sirene to retrieve",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Sirene")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Sirene not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Sirene not found")
     *         )
     *     )
     * )
     */
    public function showByNumeroSerie(string $numeroSerie): JsonResponse
    {
        Gate::authorize('voir_sirene');
        return $this->sireneService->findByNumeroSerie($numeroSerie, [
            'modeleSirene',
            'ecole',
            'site',
        ]);
    }

    /**
     * Mettre à jour une sirène
     * @OA\Put(
     *     path="/api/sirenes/{id}",
     *     summary="Update an existing sirene",
     *     tags={"Sirenes"},
     *     security={ {"passport": {}} },
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the sirene to update",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/UpdateSireneRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Sirene updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Sirene")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="This action is unauthorized.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Sirene not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Sirene not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid.")
     *         )
     *     )
     * )
     */
    public function update(UpdateSireneRequest $request, string $id): JsonResponse
    {
        Gate::authorize('modifier_sirene');
        return $this->sireneService->update($id, $request->validated());
    }

    /**
     * Affecter une sirène à un site
     * @OA\Post(
     *     path="/api/sirenes/{id}/affecter",
     *     summary="Affect a sirene to a site",
     *     tags={"Sirenes"},
     *     security={ {"passport": {}} },
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the sirene to affect",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/AffecterSireneRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Sirene affected successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Sirène affectée avec succès.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="This action is unauthorized.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Sirene or Site not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Sirene or Site not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid.")
     *         )
     *     )
     * )
     */
    public function affecter(AffecterSireneRequest $request, string $id): JsonResponse
    {
        Gate::authorize('modifier_sirene');
        return $this->sireneService->affecterSireneASite($id, $request->site_id, $request->ecole_id ?? null);
    }

    /**
     * Obtenir les sirènes disponibles (non affectées)
     * @OA\Get(
     *     path="/api/sirenes/disponibles",
     *     summary="Get available sirenes (not assigned to any site)",
     *     tags={"Sirenes"},
     *     security={ {"passport": {}} },
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Sirene"))
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function disponibles(): JsonResponse
    {
        Gate::authorize('voir_les_sirenes');
        return $this->sireneService->getSirenesDisponibles(['modeleSirene']);
    }

    /**
     * Supprimer une sirène
     * @OA\Delete(
     *     path="/api/sirenes/{id}",
     *     summary="Delete a sirene by ID",
     *     tags={"Sirenes"},
     *     security={ {"passport": {}} },
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the sirene to delete",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Sirene deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Sirene not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Sirene not found")
     *         )
     *     )
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        Gate::authorize('supprimer_sirene');
        return $this->sireneService->delete($id);
    }

    /**
     * Obtenir la configuration ESP8266 d'une sirène par numéro de série
     * Endpoint public utilisé par les modules ESP8266 au démarrage
     *
     * @OA\Get(
     *     path="/api/sirenes/config/{numeroSerie}",
     *     summary="Get ESP8266 configuration by serial number (Public endpoint)",
     *     tags={"Sirenes"},
     *     @OA\Parameter(
     *         name="numeroSerie",
     *         in="path",
     *         required=true,
     *         description="Serial number of the sirene",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Configuration retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Configuration ESP8266 récupérée avec succès."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="numero_serie", type="string", example="SRN-2024-001"),
     *                 @OA\Property(property="token_crypte", type="string", description="Token d'authentification crypté"),
     *                 @OA\Property(property="programmations", type="array", @OA\Items(type="object",
     *                     @OA\Property(property="id", type="string", format="ulid"),
     *                     @OA\Property(property="nom", type="string"),
     *                     @OA\Property(property="chaine_cryptee", type="string", description="Programmation cryptée")
     *                 ))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Sirene not found or no active subscription",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function getConfig(string $numeroSerie): JsonResponse
    {
        try {
            // 1. Rechercher la sirène par numéro de série
            $sirene = \App\Models\Sirene::where('numero_serie', $numeroSerie)
                ->with([
                    'ecole',
                    'site',
                    'abonnements' => function ($query) {
                        $query->where('statut', \App\Enums\StatutAbonnement::ACTIF->value)
                            ->where('date_debut', '<=', now())
                            ->where('date_fin', '>=', now())
                            ->with(['tokenActif']);
                    },
                    'programmations' => function ($query) {
                        $query->where('actif', true)
                            ->where('date_debut', '<=', now())
                            ->where('date_fin', '>=', now());
                    }
                ])
                ->first();

            if (!$sirene) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sirène non trouvée avec ce numéro de série.',
                ], 404);
            }

            // 2. Vérifier qu'il existe un abonnement actif
            $abonnementActif = $sirene->abonnements->first();

            if (!$abonnementActif) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun abonnement actif trouvé pour cette sirène.',
                ], 404);
            }

            // 3. Récupérer le token actif
            $tokenActif = $abonnementActif->tokenActif;

            if (!$tokenActif) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun token actif trouvé pour cet abonnement.',
                ], 404);
            }

            // 4. Récupérer les programmations actives
            $programmations = $sirene->programmations->map(function ($prog) {
                return [
                    'id' => $prog->id,
                    'nom' => $prog->nom_programmation,
                    'chaine_cryptee' => $prog->chaine_cryptee,
                    'date_debut' => $prog->date_debut->format('Y-m-d'),
                    'date_fin' => $prog->date_fin->format('Y-m-d'),
                ];
            });

            // 5. Retourner la configuration
            return response()->json([
                'success' => true,
                'message' => 'Configuration ESP8266 récupérée avec succès.',
                'data' => [
                    'numero_serie' => $sirene->numero_serie,
                    'ecole' => [
                        'id' => $sirene->ecole->id ?? null,
                        'nom' => $sirene->ecole->nom ?? null,
                    ],
                    'site' => [
                        'id' => $sirene->site->id ?? null,
                        'nom' => $sirene->site->nom ?? null,
                    ],
                    'token_crypte' => $tokenActif->token_crypte,
                    'token_valide_jusqu_au' => $tokenActif->date_expiration->toIso8601String(),
                    'programmations' => $programmations,
                ],
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Erreur lors de la récupération de la config ESP8266', [
                'numero_serie' => $numeroSerie,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération de la configuration.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Obtenir les programmations actives cryptées pour la sirène authentifiée
     * Endpoint public utilisé par les modules ESP8266 pour récupérer les programmations
     * La sirène est identifiée via le token dans le header X-Sirene-Token
     *
     * @OA\Get(
     *     path="/api/sirenes/programmations-actives",
     *     summary="Get active encrypted programmation for authenticated sirene (Token-based auth)",
     *     tags={"Sirenes"},
     *     @OA\Parameter(
     *         name="X-Sirene-Token",
     *         in="header",
     *         required=true,
     *         description="Token crypté d'authentification de la sirène (obtenu via /api/sirenes/config/{numeroSerie})",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Programmation retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="chaine_cryptee", type="string", description="Base64 encrypted programmation string"),
     *                 @OA\Property(property="chaine_programmee", type="string", description="Human readable programmation string"),
     *                 @OA\Property(property="version", type="string", example="01", description="Programmation version"),
     *                 @OA\Property(property="date_generation", type="string", format="date-time", example="2025-11-19 15:30:00", description="Generation timestamp"),
     *                 @OA\Property(property="date_debut", type="string", format="date", example="2024-09-01", description="Start date"),
     *                 @OA\Property(property="date_fin", type="string", format="date", example="2024-12-20", description="End date")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Invalid or missing token",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No active programmation found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function getProgrammation(Request $request): JsonResponse
    {
        // L'authentification est gérée par le middleware AuthenticateEsp8266
        // La sirène a été identifiée via le token et ajoutée au request
        $sirene = $request->get('authenticated_sirene');

        return $this->sireneService->getProgrammationForSirene($sirene);
    }
}
