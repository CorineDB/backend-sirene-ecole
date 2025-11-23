<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProgrammationRequest;
use App\Http\Requests\UpdateProgrammationRequest;
use App\Models\Programmation;
use App\Models\Sirene;
use App\Services\Contracts\ProgrammationServiceInterface;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Programmations",
 *     description="Gestion des programmations de sonneries"
 * )
 *
 * @OA\Schema(
 *     schema="Programmation",
 *     title="Programmation",
 *     description="Détails d'une programmation de sonnerie",
 *     @OA\Property(property="id", type="string", format="ulid", description="ID de la programmation"),
 *     @OA\Property(property="ecole_id", type="string", format="ulid", description="ID de l'école associée"),
 *     @OA\Property(property="site_id", type="string", format="ulid", description="ID du site associé"),
 *     @OA\Property(property="sirene_id", type="string", format="ulid", description="ID de la sirène associée"),
 *     @OA\Property(property="abonnement_id", type="string", format="ulid", description="ID de l'abonnement associé"),
 *     @OA\Property(property="calendrier_id", type="string", format="ulid", nullable=true, description="ID du calendrier scolaire associé"),
 *     @OA\Property(property="nom_programmation", type="string", description="Nom de la programmation"),
 *     @OA\Property(property="horaires_sonneries", type="array", @OA\Items(type="string", format="time"), description="Horaires des sonneries"),
 *     @OA\Property(property="jour_semaine", type="array", @OA\Items(type="integer"), description="Jours de la semaine concernés (calculé dynamiquement à partir de horaires_sonneries, 0=Dimanche...6=Samedi, lecture seule)"),
 *     @OA\Property(property="jours_feries_inclus", type="boolean", description="Indique si les jours fériés sont inclus"),
 *     @OA\Property(property="jours_feries_exceptions", type="array", @OA\Items(type="string", format="date"), nullable=true, description="Exceptions pour les jours fériés"),
 *     @OA\Property(property="chaine_programmee", type="string", nullable=true, description="Chaîne de programmation générée"),
 *     @OA\Property(property="chaine_cryptee", type="string", nullable=true, description="Chaîne de programmation cryptée"),
 *     @OA\Property(property="date_debut", type="string", format="date", nullable=true, description="Date de début de la programmation"),
 *     @OA\Property(property="date_fin", type="string", format="date", nullable=true, description="Date de fin de la programmation"),
 *     @OA\Property(property="actif", type="boolean", description="Indique si la programmation est active"),
 *     @OA\Property(property="cree_par", type="string", format="ulid", nullable=true, description="ID de l'utilisateur ayant créé la programmation"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 * )
 */
class ProgrammationController extends Controller
{
    use JsonResponseTrait;

    /**
     * @var ProgrammationServiceInterfaceqp
     */
    protected $programmationService;

    /**
     * @param ProgrammationServiceInterface $programmationService
     */
    public function __construct(ProgrammationServiceInterface $programmationService)
    {
        $this->programmationService = $programmationService;
    }

    /**
     * @OA\Get(
     *     path="/api/sirenes/{sirene}/programmations",
     *     tags={"Programmations"},
     *     summary="Lister les programmations d'une sirène (paginé)",
     *     @OA\Parameter(name="sirene", in="path", required=true, @OA\Schema(type="string"), description="ID de la sirène"),
     *     @OA\Parameter(name="date", in="query", @OA\Schema(type="string", format="date"), description="Filtrer par date spécifique (retourne liste non paginée)"),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1), description="Numéro de la page"),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=15, maximum=100), description="Nombre d'éléments par page (max: 100)"),
     *     @OA\Response(
     *         response=200,
     *         description="Success (paginé)",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Programmations paginées récupérées avec succès."),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Programmation")),
     *             @OA\Property(
     *                 property="pagination",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="total", type="integer", example=45),
     *                 @OA\Property(property="last_page", type="integer", example=3),
     *                 @OA\Property(property="from", type="integer", example=1),
     *                 @OA\Property(property="to", type="integer", example=15),
     *                 @OA\Property(property="has_more_pages", type="boolean", example=true)
     *             ),
     *             @OA\Property(
     *                 property="links",
     *                 type="object",
     *                 @OA\Property(property="first", type="string", example="http://api.example.com/api/sirenes/01ABC/programmations?page=1"),
     *                 @OA\Property(property="last", type="string", example="http://api.example.com/api/sirenes/01ABC/programmations?page=3"),
     *                 @OA\Property(property="prev", type="string", nullable=true, example=null),
     *                 @OA\Property(property="next", type="string", example="http://api.example.com/api/sirenes/01ABC/programmations?page=2")
     *             )
     *         )
     *     )
     * )
     */
    public function index(Sirene $sirene, Request $request): JsonResponse
    {
        Gate::authorize('voir_les_programmations');

        $date = $request->query('date');

        // Si une date est spécifiée, retourner les programmations effectives (non paginé)
        if ($date) {
            return $this->programmationService->getEffectiveProgrammationsForSirene($sirene->id, $date);
        }

        // Sinon, retourner les programmations paginées
        $perPage = min((int) $request->query('per_page', 15), 100); // Max 100 items per page

        return $this->programmationService->getPaginatedBySireneId($sirene->id, $perPage);
    }

    /**
     * @OA\Post(
     *     path="/api/sirenes/{sirene}/programmations",
     *     tags={"Programmations"},
     *     summary="Créer une programmation pour une sirène",
     *     @OA\Parameter(name="sirene", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(@OA\JsonContent(ref="#/components/schemas/StoreProgrammationRequest")),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(ref="#/components/schemas/Programmation")),
     *     @OA\Response(response=422, description="Unprocessable Entity")
     * )
     */
    public function store(StoreProgrammationRequest $request, Sirene $sirene): JsonResponse
    {
        Gate::authorize('creer_programmation');

        $data = array_merge($request->validated(), ['sirene_id' => $sirene->id]);
        return $this->programmationService->create($data);
    }

    /**
     * @OA\Get(
     *     path="/api/sirenes/{sirene}/programmations/{programmation}",
     *     tags={"Programmations"},
     *     summary="Afficher une programmation",
     *     @OA\Parameter(name="sirene", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="programmation", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Success", @OA\JsonContent(ref="#/components/schemas/Programmation")),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */
    public function show(Sirene $sirene, Programmation $programmation): JsonResponse
    {
        Gate::authorize('voir_programmation');

        return $this->programmationService->findBy([
            'sirene_id' => $sirene->id,
            'id' => $programmation->id,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/sirenes/{sirene}/programmations/{programmation}",
     *     tags={"Programmations"},
     *     summary="Mettre à jour une programmation",
     *     @OA\Parameter(name="sirene", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="programmation", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(@OA\JsonContent(ref="#/components/schemas/UpdateProgrammationRequest")),
     *     @OA\Response(response=200, description="Success", @OA\JsonContent(ref="#/components/schemas/Programmation")),
     *     @OA\Response(response=404, description="Not Found"),
     *     @OA\Response(response=422, description="Unprocessable Entity")
     * )
     */
    public function update(UpdateProgrammationRequest $request, Sirene $sirene, Programmation $programmation): JsonResponse
    {
        Gate::authorize('modifier_programmation');

        $data = array_merge($request->validated(), ['sirene_id' => $sirene->id]);
        return $this->programmationService->update($programmation->id, $data);
    }

    /**
     * @OA\Delete(
     *     path="/api/sirenes/{sirene}/programmations/{programmation}",
     *     tags={"Programmations"},
     *     summary="Supprimer une programmation",
     *     @OA\Parameter(name="sirene", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="programmation", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=204, description="No Content"),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */
    public function destroy(Sirene $sirene, Programmation $programmation): JsonResponse
    {
        Gate::authorize('supprimer_programmation');

        return $this->programmationService->delete($programmation->id);
    }
}
