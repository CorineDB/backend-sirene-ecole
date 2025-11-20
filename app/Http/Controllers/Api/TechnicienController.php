<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Technicien\CreateTechnicienRequest;
use App\Http\Requests\Technicien\UpdateTechnicienRequest;
use App\Repositories\Contracts\TechnicienRepositoryInterface;
use App\Services\Contracts\TechnicienServiceInterface;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Class TechnicienController
 * @package App\Http\Controllers
 * @OA\Tag(
 *     name="Techniciens",
 *     description="API Endpoints for Technicien Management"
 * )
 * @OA\Schema(
 *     schema="Technicien",
 *     title="Technicien",
 *     description="Technicien model",
 *     @OA\Property(property="id", type="string", format="uuid", description="ID of the technician"),
 *     @OA\Property(property="ville_id", type="string", format="uuid", description="ID of the city"),
 *     @OA\Property(property="review", type="number", format="float", description="Technician's review score"),
 *     @OA\Property(property="specialite", type="string", description="Technician's specialty"),
 *     @OA\Property(property="disponibilite", type="boolean", description="Technician's availability"),
 *     @OA\Property(property="date_inscription", type="string", format="date", description="Date of inscription"),
 *     @OA\Property(property="statut", type="string", description="Technician's status"),
 *     @OA\Property(property="date_embauche", type="string", format="date", description="Date of hire"),
 *     @OA\Property(property="user", type="object", description="Associated user details"),
 * )
 */
class TechnicienController extends Controller
{
    use JsonResponseTrait;

    protected TechnicienServiceInterface $techniqueService;

    public function __construct(TechnicienServiceInterface $techniqueService)
    {
        $this->techniqueService = $techniqueService;
        /* $this->middleware('can:voir_les_techniciens')->only('index');
        $this->middleware('can:creer_technicien')->only('store');
        $this->middleware('can:voir_technicien')->only('show');
        $this->middleware('can:modifier_technicien')->only('update');
        $this->middleware('can:supprimer_technicien')->only('destroy'); */
    }

    /**
     * Display a listing of the resource.
     * @OA\Get(
     *     path="/api/techniciens",
     *     summary="List all technicians",
     *     tags={"Techniciens"},
     *     security={ {"passport": {}} },
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Technicien"))
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
    public function index(): JsonResponse
    {
        Gate::authorize('voir_les_techniciens');
        return $this->techniqueService->getAll(15, ['user.userInfo']);
    }

    /**
     * Store a newly created resource in storage.
     * @OA\Post(
     *     path="/api/techniciens",
     *     summary="Create a new technician",
     *     tags={"Techniciens"},
     *     security={ {"passport": {}} },
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/CreateTechnicienRequest")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Technician created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Technicien")
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
    public function store(CreateTechnicienRequest $request): JsonResponse
    {
        Gate::authorize('creer_technicien');
        return $this->techniqueService->create($request->validated());
    }

    /**
     * Display the specified resource.
     * @OA\Get(
     *     path="/api/techniciens/{id}",
     *     summary="Get technician details by ID",
     *     tags={"Techniciens"},
     *     security={ {"passport": {}} },
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the technician to retrieve",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Technicien")
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
     *         description="Technician not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Technician not found")
     *         )
     *     )
     * )
     */
    public function show(string $id): JsonResponse
    {
        Gate::authorize('voir_technicien');
        return $this->techniqueService->getById($id, relations:['user.userInfo']);
    }

    /**
     * Update the specified resource in storage.
     * @OA\Put(
     *     path="/api/techniciens/{id}",
     *     summary="Update an existing technician",
     *     tags={"Techniciens"},
     *     security={ {"passport": {}} },
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the technician to update",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/UpdateTechnicienRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Technician updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Technicien")
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
     *         description="Technician not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Technician not found")
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
    public function update(UpdateTechnicienRequest $request, string $id): JsonResponse
    {
        Gate::authorize('modifier_technicien');
        return $this->techniqueService->update($id, $request->validated());
    }

    /**
     * Remove the specified resource from storage.
     * @OA\Delete(
     *     path="/api/techniciens/{id}",
     *     summary="Delete a technician by ID",
     *     tags={"Techniciens"},
     *     security={ {"passport": {}} },
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the technician to delete",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Technician deleted successfully"
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
     *         description="Technician not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Technician not found")
     *         )
     *     )
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        \Illuminate\Support\Facades\Gate::authorize('supprimer_technicien');
        return $this->techniqueService->delete($id);
    }

    /**
     * Récupérer toutes les interventions d'un technicien
     *
     * @OA\Get(
     *     path="/api/techniciens/{id}/interventions",
     *     summary="Récupérer les interventions d'un technicien",
     *     tags={"Techniciens"},
     *     security={ {"passport": {}} },
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID du technicien",
     *         @OA\Schema(type="string", format="ulid")
     *     ),
     *     @OA\Parameter(
     *         name="statut",
     *         in="query",
     *         required=false,
     *         description="Filtrer par statut (planifie, en_cours, termine, annule)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des interventions du technicien",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Intervention")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Technicien non trouvé"
     *     )
     * )
     */
    public function getInterventions(string $id): JsonResponse
    {
        try {
            $technicien = \App\Models\Technicien::findOrFail($id);

            // Récupérer les paramètres de filtrage
            $statut = request()->query('statut');

            // Construire la requête avec relations
            $query = $technicien->interventions()
                ->with([
                    'ecole',
                    'site',
                    'panne',
                    'ordreMission',
                    'rapportIntervention'
                ]);

            // Filtrer par statut si fourni
            if ($statut) {
                $query->where('statut', $statut);
            }

            // Trier par date (plus récentes en premier)
            $interventions = $query->orderBy('date_intervention', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $interventions
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Technicien non trouvé'
            ], 404);

        } catch (\Exception $e) {
            \Log::error('Erreur récupération interventions technicien: ' . $e->getMessage(), [
                'technicien_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des interventions'
            ], 500);
        }
    }
}
