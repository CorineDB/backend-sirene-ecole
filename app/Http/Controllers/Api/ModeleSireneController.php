<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ModeleSirene\CreateModeleSireneRequest;
use App\Http\Requests\Api\ModeleSirene\UpdateModeleSireneRequest;
use App\Services\Contracts\ModeleSireneServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ModeleSireneController extends Controller
{
    protected ModeleSireneServiceInterface $modeleSireneService;

    public function __construct(ModeleSireneServiceInterface $modeleSireneService)
    {
        $this->modeleSireneService = $modeleSireneService;
    }

    public function index(): JsonResponse
    {
        Gate::authorize('voir_les_modeles_sirene');
        return $this->modeleSireneService->getAll();
    }

    public function store(CreateModeleSireneRequest $request): JsonResponse
    {
        Gate::authorize('creer_modele_sirene');
        return $this->modeleSireneService->create($request->validated());
    }

    public function show(string $id): JsonResponse
    {
        Gate::authorize('voir_modele_sirene');
        return $this->modeleSireneService->getById($id);
    }

    public function update(UpdateModeleSireneRequest $request, string $id): JsonResponse
    {
        Gate::authorize('modifier_modele_sirene');
        return $this->modeleSireneService->update($id, $request->validated());
    }

    public function destroy(string $id): JsonResponse
    {
        Gate::authorize('supprimer_modele_sirene');
        return $this->modeleSireneService->delete($id);
    }
}
