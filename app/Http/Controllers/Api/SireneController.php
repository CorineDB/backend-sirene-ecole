<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sirene\AffecterSireneRequest;
use App\Http\Requests\Sirene\CreateSireneRequest;
use App\Http\Requests\Sirene\UpdateSireneRequest;
use App\Services\Contracts\SireneServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SireneController extends Controller
{
    protected $sireneService;

    public function __construct(SireneServiceInterface $sireneService)
    {
        $this->sireneService = $sireneService;
    }

    /**
     * Lister toutes les sirènes
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        return $this->sireneService->paginate($perPage, ['modeleSirene', 'ecole', 'site']);
    }

    /**
     * Créer une nouvelle sirène (Admin seulement - génération à l'usine)
     */
    public function store(CreateSireneRequest $request): JsonResponse
    {
        return $this->sireneService->create($request->validated());
    }

    /**
     * Afficher les détails d'une sirène
     */
    public function show(string $id): JsonResponse
    {
        return $this->sireneService->getById($id, [
            'modeleSirene',
            'ecole',
            'site.ecolePrincipale',
            'abonnements',
            'pannes',
        ]);
    }

    /**
     * Rechercher une sirène par numéro de série
     */
    public function showByNumeroSerie(string $numeroSerie): JsonResponse
    {
        return $this->sireneService->findByNumeroSerie($numeroSerie, [
            'modeleSirene',
            'ecole',
            'site.ecolePrincipale',
        ]);
    }

    /**
     * Mettre à jour une sirène
     */
    public function update(UpdateSireneRequest $request, string $id): JsonResponse
    {
        return $this->sireneService->update($id, $request->validated());
    }

    /**
     * Affecter une sirène à un site
     */
    public function affecter(AffecterSireneRequest $request, string $id): JsonResponse
    {
        return $this->sireneService->affecterSireneASite($id, $request->site_id);
    }

    /**
     * Obtenir les sirènes disponibles (non affectées)
     */
    public function disponibles(): JsonResponse
    {
        return $this->sireneService->getSirenesDisponibles(['modeleSirene']);
    }

    /**
     * Supprimer une sirène
     */
    public function destroy(string $id): JsonResponse
    {
        return $this->sireneService->delete($id);
    }
}
