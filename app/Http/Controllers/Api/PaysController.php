<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Pays\CreatePaysRequest;
use App\Http\Requests\Api\Pays\UpdatePaysRequest;
use App\Services\Contracts\PaysServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PaysController extends Controller
{
    protected PaysServiceInterface $paysService;

    public function __construct(PaysServiceInterface $paysService)
    {
        $this->paysService = $paysService;
    }

    public function index(): JsonResponse
    {
        Gate::authorize('voir_les_pays');
        return $this->paysService->getAll();
    }

    public function store(CreatePaysRequest $request): JsonResponse
    {
        Gate::authorize('creer_pays');
        return $this->paysService->create($request->validated());
    }

    public function show(string $id): JsonResponse
    {
        Gate::authorize('voir_pays');
        return $this->paysService->getById($id);
    }

    public function update(UpdatePaysRequest $request, string $id): JsonResponse
    {
        Gate::authorize('modifier_pays');
        return $this->paysService->update($id, $request->validated());
    }

    public function destroy(string $id): JsonResponse
    {
        Gate::authorize('supprimer_pays');
        return $this->paysService->delete($id);
    }
}
