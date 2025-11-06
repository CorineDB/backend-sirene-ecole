<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\JourFerie\CreateJourFerieRequest;
use App\Http\Requests\JourFerie\UpdateJourFerieRequest;
use App\Services\Contracts\JourFerieServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Jours Fériés",
 *     description="API Endpoints for Public Holidays Management"
 * )
 */
class JourFerieController extends Controller
{
    protected $jourFerieService;

    public function __construct(JourFerieServiceInterface $jourFerieService)
    {
        $this->jourFerieService = $jourFerieService;
    }

    /**
     * Display a listing of the public holidays.
     *
     * @OA\Get(
     *     path="/api/jours-feries",
     *     summary="List all public holidays",
     *     tags={"Jours Fériés"},
     *     security={ {"passport": {}} },
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
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/JourFerie"))
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
        $perPage = $request->get('per_page', 15);
        return $this->jourFerieService->getAll($perPage);
    }

    /**
     * Store a newly created public holiday in storage.
     *
     * @OA\Post(
     *     path="/api/jours-feries",
     *     summary="Create a new public holiday",
     *     tags={"Jours Fériés"},
     *     security={ {"passport": {}} },
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/CreateJourFerieRequest")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Public holiday created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/JourFerie")
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
    public function store(CreateJourFerieRequest $request): JsonResponse
    {
        return $this->jourFerieService->create($request->all());
    }

    /**
     * Display the specified public holiday.
     *
     * @OA\Get(
     *     path="/api/jours-feries/{id}",
     *     summary="Get a specific public holiday by ID",
     *     tags={"Jours Fériés"},
     *     security={ {"passport": {}} },
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the public holiday",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/JourFerie")
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
        return $this->jourFerieService->getById($id);
    }

    /**
     * Update the specified public holiday in storage.
     *
     * @OA\Put(
     *     path="/api/jours-feries/{id}",
     *     summary="Update a specific public holiday by ID",
     *     tags={"Jours Fériés"},
     *     security={ {"passport": {}} },
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the public holiday",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/UpdateJourFerieRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Public holiday updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/JourFerie")
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
    public function update(UpdateJourFerieRequest $request, string $id): JsonResponse
    {
        return $this->jourFerieService->update($id, $request->all());
    }

    /**
     * Remove the specified public holiday from storage.
     *
     * @OA\Delete(
     *     path="/api/jours-feries/{id}",
     *     summary="Delete a specific public holiday by ID",
     *     tags={"Jours Fériés"},
     *     security={ {"passport": {}} },
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the public holiday",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Public holiday deleted successfully"
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
        return $this->jourFerieService->delete($id);
    }

    /**
     * Display a listing of the public holidays for a specific school.
     *
     * @OA\Get(
     *     path="/api/ecoles/{ecoleId}/jours-feries",
     *     summary="List all public holidays for a specific school",
     *     tags={"Jours Fériés"},
     *     security={ {"passport": {}} },
     *     @OA\Parameter(
     *         name="ecoleId",
     *         in="path",
     *         description="ID of the school",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/JourFerie"))
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
    public function indexForEcole(string $ecoleId): JsonResponse
    {
        return $this->jourFerieService->findAllBy(['ecole_id' => $ecoleId]);
    }

    /**
     * Store a newly created or updated public holiday for a specific school.
     *
     * @OA\Post(
     *     path="/api/ecoles/{ecoleId}/jours-feries",
     *     summary="Create or update a public holiday for a specific school",
     *     tags={"Jours Fériés"},
     *     security={ {"passport": {}} },
     *     @OA\Parameter(
     *         name="ecoleId",
     *         in="path",
     *         description="ID of the school",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/CreateJourFerieRequest")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Public holiday created or updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/JourFerie")
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
    public function storeForEcole(CreateJourFerieRequest $request, string $ecoleId): JsonResponse
    {
        $data = $request->all();
        $data['ecole_id'] = $ecoleId;

        // Check if a JourFerie with the same date already exists for this school
        $existingJourFerie = $this->jourFerieService->findBy([
            'ecole_id' => $ecoleId,
            'date' => $data['date'],
        ]);

        if ($existingJourFerie) {
            // If it exists, update it
            return $this->jourFerieService->update($existingJourFerie->id, $data);
        } else {
            // Otherwise, create a new one
            return $this->jourFerieService->create($data);
        }
    }
}
