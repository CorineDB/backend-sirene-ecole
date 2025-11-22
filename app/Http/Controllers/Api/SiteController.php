<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Site\CreateSiteRequest;
use App\Http\Requests\Site\UpdateSiteRequest;
use App\Services\Contracts\SiteServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use OpenApi\Annotations as OA;

/**
 * Class SiteController
 * @package App\Http\Controllers\Api
 * @OA\Tag(
 *     name="Sites",
 *     description="API Endpoints for Site Management"
 * )
 */
class SiteController extends Controller
{
    protected $siteService;

    public function __construct(SiteServiceInterface $siteService)
    {
        $this->siteService = $siteService;
    }

    /**
     * Lister tous les sites d'une école
     * @OA\Get(
     *     path="/api/ecoles/{ecoleId}/sites",
     *     summary="List all sites of a school",
     *     tags={"Sites"},
     *     security={ {"passport": {}} },
     *     @OA\Parameter(
     *         name="ecoleId",
     *         in="path",
     *         required=true,
     *         description="ID of the school",
     *         @OA\Schema(type="string", format="ulid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function index(string $ecoleId): JsonResponse
    {
        Gate::authorize('voir_les_sites');

        // Vérifier que l'utilisateur peut voir les sites de cette école
        $user = auth()->user();
        if ($user->user_account_type_type === \App\Models\Ecole::class) {
            if ($user->user_account_type_id !== $ecoleId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez voir que les sites de votre école.',
                ], 403);
            }
        }

        return $this->siteService->getAll(1000, relations: ['ville.pays', 'sirene.modeleSirene', 'sirene.abonnementActif']);
    }

    /**
     * Créer un nouveau site (annexe)
     * @OA\Post(
     *     path="/api/sites",
     *     summary="Create a new site (annexe)",
     *     tags={"Sites"},
     *     security={ {"passport": {}} },
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/CreateSiteRequest")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Site created successfully"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(CreateSiteRequest $request): JsonResponse
    {
        Gate::authorize('creer_site');
        return $this->siteService->create($request->validated());
    }

    /**
     * Afficher les détails d'un site
     * @OA\Get(
     *     path="/api/sites/{id}",
     *     summary="Get site details by ID",
     *     tags={"Sites"},
     *     security={ {"passport": {}} },
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the site",
     *         @OA\Schema(type="string", format="ulid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Site not found"
     *     )
     * )
     */
    public function show(string $id): JsonResponse
    {
        Gate::authorize('voir_site');
        return $this->siteService->getById($id, ['*'], [
            'ville.pays',
            'ecolePrincipale',
            'sirene.modeleSirene',
            'sirene.abonnementActif'
        ]);
    }

    /**
     * Mettre à jour un site
     * @OA\Put(
     *     path="/api/sites/{id}",
     *     summary="Update a site",
     *     tags={"Sites"},
     *     security={ {"passport": {}} },
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the site",
     *         @OA\Schema(type="string", format="ulid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/UpdateSiteRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Site updated successfully"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Site not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function update(UpdateSiteRequest $request, string $id): JsonResponse
    {
        Gate::authorize('modifier_site');
        return $this->siteService->update($id, $request->validated());
    }

    /**
     * Supprimer un site (ne peut pas supprimer le site principal)
     * @OA\Delete(
     *     path="/api/sites/{id}",
     *     summary="Delete a site (cannot delete principal site)",
     *     tags={"Sites"},
     *     security={ {"passport": {}} },
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the site to delete",
     *         @OA\Schema(type="string", format="ulid")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Site deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Site not found"
     *     )
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        Gate::authorize('supprimer_site');

        // Vérifier qu'on ne supprime pas le site principal
        $site = \App\Models\Site::find($id);
        if ($site && $site->est_principale) {
            return response()->json([
                'success' => false,
                'message' => 'Le site principal ne peut pas être supprimé.',
            ], 403);
        }

        return $this->siteService->delete($id);
    }
}
