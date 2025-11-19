<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ecole\InscriptionEcoleRequest;
use App\Http\Requests\Ecole\UpdateEcoleRequest;
use App\Services\Contracts\EcoleServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use OpenApi\Annotations as OA;

/**
 * Class EcoleController
 * @package App\Http\Controllers\Api
 * @OA\Tag(
 *     name="Ecoles",
 *     description="API Endpoints for School Management"
 * )
 * @OA\Schema(
 *     schema="Ecole",
 *     title="Ecole",
 *     description="School model",
 *     @OA\Property(property="id", type="string", format="uuid", description="ID of the school"),
 *     @OA\Property(property="nom", type="string", description="Name of the school"),
 *     @OA\Property(property="nom_complet", type="string", description="Full name of the school"),
 *     @OA\Property(property="telephone_contact", type="string", description="Contact phone number"),
 *     @OA\Property(property="email_contact", type="string", format="email", nullable=true, description="Contact email"),
 *     @OA\Property(property="responsable_nom", type="string", description="Last name of the person in charge"),
 *     @OA\Property(property="responsable_prenom", type="string", description="First name of the person in charge"),
 *     @OA\Property(property="responsable_telephone", type="string", description="Phone number of the person in charge"),
 *     @OA\Property(property="statut", type="string", description="Status of the school (e.g., ACTIVE, INACTIVE)"),
 *     @OA\Property(property="abonnement_id", type="string", format="uuid", nullable=true, description="ID of the active subscription"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Creation timestamp"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="Last update timestamp")
 * )
 */
use App\Services\Contracts\CalendrierScolaireServiceInterface;
use App\Http\Requests\CalendrierFiltreRequest;
use Illuminate\Support\Facades\Gate;

class EcoleController extends Controller implements HasMiddleware
{
    protected $ecoleService;
    protected $calendrierScolaireService;

    public function __construct(EcoleServiceInterface $ecoleService, CalendrierScolaireServiceInterface $calendrierScolaireService)
    {
        $this->ecoleService = $ecoleService;
        $this->calendrierScolaireService = $calendrierScolaireService;
    }

    public static function middleware(): array
    {
        return [
            // auth:api et permissions sont appliqués dans les routes
            // inscrire est public, toutes les autres méthodes sont protégées
        ];
    }

    /**
     * Lister toutes les écoles
     * @OA\Get(
     *     path="/api/ecoles",
     *     summary="List all schools",
     *     tags={"Ecoles"},
     *     security={ {"passport": {}} },
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of schools per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Ecole"))
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
        Gate::authorize('voir_les_ecoles');
        $perPage = $request->get('per_page', 15);
        return $this->ecoleService->getAll($perPage, [
            'sites.sirene.modeleSirene',
            'sites.sirene.abonnementActif',
            'sites.sirene.abonnementEnAttente',
            'abonnementEnAttente',
            'abonnementActif',
            'user'
        ]);
    }

    /**
     * Inscription d'une nouvelle école
     * @OA\Post(
     *     path="/api/ecoles/inscrire",
     *     summary="Register a new school",
     *     tags={"Ecoles"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/InscriptionEcoleRequest")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="School registered successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Ecole")
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
    public function inscrire(InscriptionEcoleRequest $request): JsonResponse
    {
        return $this->ecoleService->inscrireEcole(
            $request->validated(),
            $request->site_principal,
            $request->sites_annexe ?? []
        );
    }

    /**
     * Obtenir les informations de l'école connectée
     * @OA\Get(
     *     path="/api/ecoles/me",
     *     summary="Get authenticated school details",
     *     tags={"Ecoles"},
     *     security={ {"passport": {}} },
     *     @OA\Response(
     *         response=200,
     *         description="School details retrieved successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Ecole")
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
     *         description="School not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="School not found")
     *         )
     *     )
     * )
     */
    public function show(Request $request): JsonResponse
    {
        Gate::authorize('voir_ecole');
        return $this->ecoleService->getById($request->user()->user_account_type_id, ['*'], [
            'sites.ville.pays',
            'sites.sirene.modeleSirene',
            'sites.sirene.abonnementActif',
            'sites.sirene.abonnementEnAttente',
            'sitePrincipal.ville.pays',
            'sitePrincipal.sirene.modeleSirene',
            'sitePrincipal.sirene.abonnementActif',
            'sitePrincipal.sirene.abonnementEnAttente',
            'user'
        ]);
    }

    /**
     * Obtenir les informations d'une école par ID
     * @OA\Get(
     *     path="/api/ecoles/{id}",
     *     summary="Get school details by ID",
     *     tags={"Ecoles"},
     *     security={ {"passport": {}} },
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the school",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="School details retrieved successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Ecole")
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
     *         description="School not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="School not found")
     *         )
     *     )
     * )
     */
    public function showById(string $id): JsonResponse
    {
        Gate::authorize('voir_ecole');
        return $this->ecoleService->getById($id, ['*'], [
            'sitesAnnexe.ville.pays',
            'sitesAnnexe.sirene.modeleSirene',
            'sitesAnnexe.sirene.abonnementActif',
            'sitesAnnexe.sirene.abonnementEnAttente',
            'sitePrincipal.ville.pays',
            'sitePrincipal.sirene.modeleSirene',
            'sitePrincipal.sirene.abonnementActif',
            'sitePrincipal.sirene.abonnementEnAttente',
            'user'
        ]);
    }

    /**
     * Mettre à jour les informations de l'école connectée
     * @OA\Put(
     *     path="/api/ecoles/me",
     *     summary="Update authenticated school details",
     *     tags={"Ecoles"},
     *     security={ {"passport": {}} },
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/UpdateEcoleRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="School details updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Ecole")
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
     *         description="School not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="School not found")
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
    public function update(UpdateEcoleRequest $request): JsonResponse
    {
        Gate::authorize('modifier_ecole');
        return $this->ecoleService->update($request->user()->user_account_type_id, $request->validated());
    }

    /**
     * Mettre à jour les informations d'une école par ID
     * @OA\Put(
     *     path="/api/ecoles/{id}",
     *     summary="Update school details by ID",
     *     tags={"Ecoles"},
     *     security={ {"passport": {}} },
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the school",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/UpdateEcoleRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="School details updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Ecole")
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
     *         description="School not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="School not found")
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
    public function updateById(UpdateEcoleRequest $request, string $id): JsonResponse
    {
        Gate::authorize('modifier_ecole');
        return $this->ecoleService->update($id, $request->validated());
    }

    /**
     * Supprimer une école
     * @OA\Delete(
     *     path="/api/ecoles/{id}",
     *     summary="Delete a school by ID",
     *     tags={"Ecoles"},
     *     security={ {"passport": {}} },
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the school to delete",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="School deleted successfully"
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
     *         description="School not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="School not found")
     *         )
     *     )
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        Gate::authorize('supprimer_ecole');
        return $this->ecoleService->delete($id);
    }

    /**
     * Load the school calendar for the authenticated school, including global and school-specific holidays.
     *
     * @OA\Get(
     *     path="/api/ecoles/me/calendrier-scolaire/with-ecole-holidays",
     *     summary="Load school calendar with merged holidays for authenticated school",
     *     tags={"Ecoles"},
     *     security={ {"passport": {}} },
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="string", format="uuid"),
     *             @OA\Property(property="annee_scolaire", type="string"),
     *             @OA\Property(property="jours_feries_merged", type="array", @OA\Items(type="object"))
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
     *         response=404,
     *         description="School calendar or school not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="School calendar or school not found.")
     *         )
     *     )
     * )
     */
    public function getCalendrierScolaireWithJoursFeries(CalendrierFiltreRequest $request): JsonResponse
    {
        Gate::authorize('voir_ecole');
        $filtres = $request->getFiltres();
        if (!isset($filtres['ecoleId'])) {
            $filtres['ecoleId'] = auth()->user()->user_account_type_id;
        }
        return $this->calendrierScolaireService->getCalendrierScolaireWithJoursFeries($filtres);
    }
}
