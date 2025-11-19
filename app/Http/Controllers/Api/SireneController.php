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
}
