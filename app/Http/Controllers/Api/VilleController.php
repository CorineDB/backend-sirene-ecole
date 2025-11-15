<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ville\CreateVilleRequest;
use App\Http\Requests\Api\Ville\UpdateVilleRequest;
use App\Services\Contracts\VilleServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class VilleController extends Controller
{
    protected VilleServiceInterface $villeService;

    public function __construct(VilleServiceInterface $villeService)
    {
        $this->villeService = $villeService;
    }

    public function index(): JsonResponse
    {
        Gate::authorize('voir_les_villes');
        return $this->villeService->getAll();
    }

    public function store(CreateVilleRequest $request): JsonResponse
    {
        Gate::authorize('creer_ville');
        return $this->villeService->create($request->validated());
    }

    public function show(string $id): JsonResponse
    {
        Gate::authorize('voir_ville');
        return $this->villeService->getById($id);
    }

    public function update(UpdateVilleRequest $request, string $id): JsonResponse
    {
        Gate::authorize('modifier_ville');
        return $this->villeService->update($id, $request->validated());
    }

    public function destroy(string $id): JsonResponse
    {
        Gate::authorize('supprimer_ville');
        return $this->villeService->delete($id);
    }
}
