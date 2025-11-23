<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Contracts\DashboardServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    protected DashboardServiceInterface $dashboardService;

    public function __construct(DashboardServiceInterface $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    public function statistiquesEcole(): JsonResponse
    {
        Gate::authorize('voir_ecole');
        return $this->dashboardService->getStatistiquesEcole();
    }

    public function statistiquesTechnicien(): JsonResponse
    {
        Gate::authorize('voir_technicien');
        return $this->dashboardService->getStatistiquesTechnicien();
    }

    public function interventionsEnCours(Request $request): JsonResponse
    {
        Gate::authorize('voir_les_interventions');
        $filters = $request->only(['ecole_id', 'site_id', 'technicien_id', 'statut', 'date_debut', 'date_fin']);
        $perPage = $request->query('per_page') ? (int) $request->query('per_page') : null;
        return $this->dashboardService->getInterventionsEnCours($filters, $perPage);
    }

    public function interventionsDuJour(Request $request): JsonResponse
    {
        Gate::authorize('voir_les_interventions');
        $filters = $request->only(['ecole_id', 'site_id', 'technicien_id', 'statut']);
        $perPage = $request->query('per_page') ? (int) $request->query('per_page') : null;
        return $this->dashboardService->getInterventionsDuJour($filters, $perPage);
    }

    public function interventionsAVenir(Request $request): JsonResponse
    {
        Gate::authorize('voir_les_interventions');
        $filters = $request->only(['ecole_id', 'site_id', 'technicien_id', 'statut', 'date_debut', 'date_fin']);
        $perPage = $request->query('per_page') ? (int) $request->query('per_page') : null;
        return $this->dashboardService->getInterventionsAVenir($filters, $perPage);
    }

    public function ordresMissionDisponibles(Request $request): JsonResponse
    {
        Gate::authorize('voir_les_ordres_mission');
        $filters = $request->only(['ecole_id', 'ville_id', 'priorite']);
        $perPage = $request->query('per_page') ? (int) $request->query('per_page') : null;
        return $this->dashboardService->getOrdresMissionDisponibles($filters, $perPage);
    }
}
