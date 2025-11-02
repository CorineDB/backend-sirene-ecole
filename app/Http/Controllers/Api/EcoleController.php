<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ecole\InscriptionEcoleRequest;
use App\Http\Requests\Ecole\UpdateEcoleRequest;
use App\Services\Contracts\EcoleServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EcoleController extends Controller
{
    protected $ecoleService;

    public function __construct(EcoleServiceInterface $ecoleService)
    {
        $this->ecoleService = $ecoleService;
    }

    /**
     * Lister toutes les écoles
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        return $this->ecoleService->getAll($perPage, ['sites', 'abonnementActif', 'user']);
    }

    /**
     * Inscription d'une nouvelle école
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
     */
    public function show(Request $request): JsonResponse
    {
        return $this->ecoleService->getById($request->user()->user_account_type_id);
    }

    /**
     * Mettre à jour les informations de l'école
     */
    public function update(UpdateEcoleRequest $request): JsonResponse
    {
        return $this->ecoleService->update($request->user()->user_account_type_id, $request->validated());
    }

    /**
     * Supprimer une école
     */
    public function destroy(string $id): JsonResponse
    {
        return $this->ecoleService->delete($id);
    }
}
