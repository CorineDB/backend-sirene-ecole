<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CalendrierScolaire\CreateCalendrierScolaireRequest;
use App\Http\Requests\CalendrierScolaire\StoreMultipleJoursFeriesRequest;
use App\Http\Requests\CalendrierScolaire\UpdateMultipleJoursFeriesRequest;
use App\Http\Requests\CalendrierScolaire\UpdateCalendrierScolaireRequest;
use App\Http\Requests\JoursFeriesFiltreRequest;
use App\Services\Contracts\CalendrierScolaireServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Calendrier Scolaire",
 *     description="API Endpoints for School Calendar Management"
 * )
 * @OA\Schema(
 *     schema="CalendrierScolaire",
 *     title="Calendrier Scolaire",
 *     description="Calendrier Scolaire model",
 *     @OA\Property(property="id", type="string", format="ulid"),
 *     @OA\Property(property="pays_id", type="string", format="ulid"),
 *     @OA\Property(property="annee_scolaire", type="string", example="2023-2024"),
 *     @OA\Property(property="description", type="string"),
 *     @OA\Property(property="date_rentree", type="string", format="date"),
 *     @OA\Property(property="date_fin_annee", type="string", format="date"),
 *     @OA\Property(property="periodes_vacances", type="array", @OA\Items(type="string")),
 *     @OA\Property(property="jours_feries_defaut", type="array", @OA\Items(type="string")),
 *     @OA\Property(property="actif", type="boolean"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 * )
 * @OA\Schema(
 *     schema="JourFerie",
 *     title="Jour Ferie",
 *     description="Jour Ferie model",
 *     @OA\Property(property="id", type="string", format="ulid"),
 *     @OA\Property(property="calendrier_id", type="string", format="ulid"),
 *     @OA\Property(property="ecole_id", type="string", format="ulid"),
 *     @OA\Property(property="pays_id", type="string", format="ulid"),
 *     @OA\Property(property="intitule_journee", type="string"),
 *     @OA\Property(property="date", type="string", format="date"),
 *     @OA\Property(property="recurrent", type="boolean"),
 *     @OA\Property(property="actif", type="boolean"),
 *     @OA\Property(property="est_national", type="boolean"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 * )
 */
class CalendrierScolaireController extends Controller
{
    protected $calendrierScolaireService;

    public function __construct(CalendrierScolaireServiceInterface $calendrierScolaireService)
    {
        $this->calendrierScolaireService = $calendrierScolaireService;
    }

    /**
     * Display a listing of the school calendar entries.
     *
     * @OA\Get(
     *     path="/api/calendrier-scolaire",
     *     summary="List all school calendar entries",
     *     tags={"Calendrier Scolaire"},
     *     security={ {"passport": {}} },
     *     @OA\Parameter(
     *         name="code_iso",
     *         in="query",
     *         description="Filter by country ISO code (required)",
     *         required=true,
     *         @OA\Schema(type="string", example="CI")
     *     ),
     *     @OA\Parameter(
     *         name="annee_scolaire",
     *         in="query",
     *         description="Filter by school year (optional, format: YYYY-YYYY)",
     *         required=false,
     *         @OA\Schema(type="string", example="2024-2025")
     *     ),
     *     @OA\Parameter(
     *         name="actif",
     *         in="query",
     *         description="Filter by active status (optional)",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of entries per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/CalendrierScolaire"))
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Missing required parameters",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Le paramètre code_iso est obligatoire.")
     *         )
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
        Gate::authorize('voir_les_calendriers_scolaires');

        // Vérifier que code_iso est présent (obligatoire)
        $codeIso = $request->get('code_iso');
        $anneeScolaire = $request->get('annee_scolaire');

        if (!$codeIso) {
            return response()->json([
                'success' => false,
                'message' => 'Le paramètre code_iso est obligatoire.'
            ], 400);
        }

        // Construire les filtres optionnels
        $filters = [];
        if ($request->has('actif')) {
            $filters['actif'] = filter_var($request->get('actif'), FILTER_VALIDATE_BOOLEAN);
        }

        // Déléguer la logique au service
        return $this->calendrierScolaireService->findByCodeIsoAndAnneeScolaire($codeIso, $anneeScolaire, $filters);
    }

    /**
     * Store a newly created school calendar entry in storage.
     *
     * @OA\Post(
     *     path="/api/calendrier-scolaire",
     *     summary="Create a new school calendar entry",
     *     tags={"Calendrier Scolaire"},
     *     security={ {"passport": {}} },
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/CreateCalendrierScolaireRequest")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="School calendar entry created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/CalendrierScolaire")
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
    public function store(CreateCalendrierScolaireRequest $request): JsonResponse
    {
        Gate::authorize('creer_calendrier_scolaire');
        return $this->calendrierScolaireService->create($request->all());
    }

    /**
     * Display the specified school calendar entry.
     *
     * @OA\Get(
     *     path="/api/calendrier-scolaire/{id}",
     *     summary="Get a specific school calendar entry by ID",
     *     tags={"Calendrier Scolaire"},
     *     security={ {"passport": {}} },
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the school calendar entry",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/CalendrierScolaire")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Entry not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Entry not found.")
     *         )
     *     )
     * )
     */
    public function show(string $id): JsonResponse
    {
        Gate::authorize('voir_calendrier_scolaire');
        return $this->calendrierScolaireService->getById($id);
    }

    /**
     * Update the specified school calendar entry in storage.
     *
     * @OA\Put(
     *     path="/api/calendrier-scolaire/{id}",
     *     summary="Update a specific school calendar entry by ID",
     *     tags={"Calendrier Scolaire"},
     *     security={ {"passport": {}} },
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the school calendar entry",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/UpdateCalendrierScolaireRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="School calendar entry updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/CalendrierScolaire")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Entry not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Entry not found.")
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
    public function update(UpdateCalendrierScolaireRequest $request, string $id): JsonResponse
    {
        Gate::authorize('modifier_calendrier_scolaire');
        return $this->calendrierScolaireService->update($id, $request->all());
    }

    /**
     * Remove the specified school calendar entry from storage.
     *
     * @OA\Delete(
     *     path="/api/calendrier-scolaire/{id}",
     *     summary="Delete a specific school calendar entry by ID",
     *     tags={"Calendrier Scolaire"},
     *     security={ {"passport": {}} },
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the school calendar entry",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="School calendar entry deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Entry not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Entry not found.")
     *         )
     *     )
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        Gate::authorize('supprimer_calendrier_scolaire');
        return $this->calendrierScolaireService->delete($id);
    }

    /**
     * Display a listing of the public holidays for a specific school calendar.
     *
     * @OA\Get(
     *     path="/api/calendrier-scolaire/{id}/jours-feries",
     *     summary="List all public holidays for a specific school calendar",
     *     tags={"Calendrier Scolaire"},
     *     security={ {"passport": {}} },
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the school calendar",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/JourFerie"))
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="School calendar not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="School calendar not found.")
     *         )
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
    public function getJoursFeries(JoursFeriesFiltreRequest $request, string $id): JsonResponse
    {
        Gate::authorize('voir_calendrier_scolaire');
        return $this->calendrierScolaireService->getJoursFeries($id, $request->getFiltres());
    }

    /**
     * Calculate the number of school days for a specific school calendar.
     *
     * @OA\Get(
     *     path="/api/calendrier-scolaire/{id}/calculate-school-days",
     *     summary="Calculate the number of school days for a specific school calendar",
     *     tags={"Calendrier Scolaire"},
     *     security={ {"passport": {}} },
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the school calendar",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="ecole_id",
     *         in="query",
     *         description="ID of the school (optional)",
     *         required=false,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="school_days", type="integer", example=180)
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="School calendar not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="School calendar not found.")
     *         )
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
    public function calculateSchoolDays(Request $request, string $id): JsonResponse
    {
        Gate::authorize('voir_calendrier_scolaire');
        $ecoleId = $request->get('ecole_id');
        return $this->calendrierScolaireService->calculateSchoolDays($id, $ecoleId);
    }

    /**
     * Store multiple public holidays for a specific school calendar.
     *
     * @OA\Post(
     *     path="/api/calendrier-scolaire/{id}/jours-feries/bulk",
     *     summary="Create or update multiple public holidays for a school calendar",
     *     tags={"Calendrier Scolaire"},
     *     security={ {"passport": {}} },
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the school calendar",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/StoreMultipleJoursFeriesRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Public holidays processed successfully",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/JourFerie"))
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
    public function storeMultipleJoursFeries(StoreMultipleJoursFeriesRequest $request, string $id): JsonResponse
    {
        Gate::authorize('creer_calendrier_scolaire');
        return $this->calendrierScolaireService->storeMultipleJoursFeries($id, $request->validated());
    }

    /**
     * Update multiple public holidays for a specific school calendar.
     *
     * @OA\Put(
     *     path="/api/calendrier-scolaire/{id}/jours-feries/bulk",
     *     summary="Update multiple public holidays for a school calendar",
     *     tags={"Calendrier Scolaire"},
     *     security={ {"passport": {}} },
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the school calendar",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/StoreMultipleJoursFeriesRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Public holidays processed successfully",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/JourFerie"))
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
    public function updateMultipleJoursFeries(UpdateMultipleJoursFeriesRequest $request, string $id): JsonResponse
    {
        Gate::authorize('modifier_calendrier_scolaire');
        return $this->calendrierScolaireService->updateMultipleJoursFeries($id, $request->validated());
    }
}
