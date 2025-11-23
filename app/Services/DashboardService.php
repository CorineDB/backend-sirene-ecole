<?php

namespace App\Services;

use App\Enums\StatutIntervention;
use App\Enums\StatutPanne;
use App\Models\Intervention;
use App\Models\OrdreMission;
use App\Models\Panne;
use App\Services\Contracts\DashboardServiceInterface;
use App\Traits\FiltersByEcole;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class DashboardService extends BaseService implements DashboardServiceInterface
{
    use FiltersByEcole;

    public function __construct()
    {
        // Pas de repository pour ce service
    }

    public function getStatistiquesEcole(): JsonResponse
    {
        try {
            $ecoleId = $this->isEcoleUser() ? $this->getEcoleId() : null;

            $pannesQuery = Panne::query();
            if ($ecoleId) {
                $pannesQuery->whereHas('site', fn($q) => $q->where('ecole_id', $ecoleId));
            }

            $interventionsQuery = Intervention::query();
            if ($ecoleId) {
                $interventionsQuery->whereHas('panne', function($q) use ($ecoleId) {
                    $q->whereHas('site', fn($siteQ) => $siteQ->where('ecole_id', $ecoleId));
                });
            }

            $totalPannes = (clone $pannesQuery)->count();
            $pannesResolues = (clone $pannesQuery)->where('statut', StatutPanne::RESOLUE)->count();
            $totalInterventions = (clone $interventionsQuery)->count();

            // Calculer délai moyen (en jours)
            $delaiMoyen = (clone $pannesQuery)
                ->whereNotNull('date_validation')
                ->where('statut', StatutPanne::RESOLUE)
                ->selectRaw('AVG(EXTRACT(EPOCH FROM (updated_at - date_validation))/86400) as delai')
                ->value('delai');

            $stats = [
                'total_pannes' => $totalPannes,
                'pannes_resolues' => $pannesResolues,
                'total_interventions' => $totalInterventions,
                'delai_moyen_jours' => round($delaiMoyen ?? 0, 1),
            ];

            return $this->successResponse('Statistiques école récupérées.', $stats);
        } catch (Exception $e) {
            Log::error("Error in DashboardService::getStatistiquesEcole - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getStatistiquesTechnicien(): JsonResponse
    {
        try {
            $user = auth()->user();
            $technicien = $user->getTechnicien();

            if (!$technicien) {
                return $this->errorResponse('Technicien non trouvé.', 404);
            }

            // Interventions terminées par ce technicien
            $interventionsTerminees = Intervention::whereHas('techniciens', function($q) use ($technicien) {
                $q->where('techniciens.id', $technicien->id);
            })->where('statut', StatutIntervention::TERMINEE)->count();

            // Taux de réussite (interventions résolues / total)
            $totalInterventions = Intervention::whereHas('techniciens', function($q) use ($technicien) {
                $q->where('techniciens.id', $technicien->id);
            })->count();

            $tauxReussite = $totalInterventions > 0
                ? round(($interventionsTerminees / $totalInterventions) * 100, 1)
                : 0;

            // Rapports rédigés
            $rapportsRediges = DB::table('rapports_intervention')
                ->where('technicien_id', $technicien->id)
                ->count();

            // Note moyenne
            $noteMoyenne = DB::table('rapports_intervention')
                ->where('technicien_id', $technicien->id)
                ->whereNotNull('note_admin')
                ->avg('note_admin');

            $stats = [
                'interventions_terminees' => $interventionsTerminees,
                'taux_reussite' => $tauxReussite,
                'rapports_rediges' => $rapportsRediges,
                'note_moyenne' => round($noteMoyenne ?? 0, 1),
                'note_max' => 5,
            ];

            return $this->successResponse('Statistiques technicien récupérées.', $stats);
        } catch (Exception $e) {
            Log::error("Error in DashboardService::getStatistiquesTechnicien - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getInterventionsEnCours(array $filters, ?int $perPage): JsonResponse
    {
        try {
            $query = Intervention::with(['panne.site', 'techniciens', 'ordreMission'])
                ->where('statut', StatutIntervention::EN_COURS);

            // Filtres
            if (!empty($filters['ecole_id'])) {
                $query->whereHas('panne.site', fn($q) => $q->where('ecole_id', $filters['ecole_id']));
            }
            if (!empty($filters['site_id'])) {
                $query->whereHas('panne', fn($q) => $q->where('site_id', $filters['site_id']));
            }
            if (!empty($filters['technicien_id'])) {
                $query->whereHas('techniciens', fn($q) => $q->where('techniciens.id', $filters['technicien_id']));
            }

            // Auto-filtre école
            if ($this->isEcoleUser()) {
                $ecoleId = $this->getEcoleId();
                $query->whereHas('panne', function($q) use ($ecoleId) {
                    $q->whereHas('site', fn($siteQ) => $siteQ->where('ecole_id', $ecoleId));
                });
            }

            $query->orderBy('date_intervention', 'asc');
            $data = $perPage ? $query->paginate($perPage) : $query->get();

            return $this->successResponse('Interventions en cours récupérées.', $data);
        } catch (Exception $e) {
            Log::error("Error in DashboardService::getInterventionsEnCours - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getInterventionsDuJour(?int $perPage): JsonResponse
    {
        try {
            $today = now()->format('Y-m-d');

            $query = Intervention::with(['panne.site', 'techniciens', 'ordreMission'])
                ->whereDate('date_intervention', $today)
                ->whereIn('statut', [StatutIntervention::PLANIFIEE, StatutIntervention::EN_COURS]);

            // Auto-filtre école
            if ($this->isEcoleUser()) {
                $ecoleId = $this->getEcoleId();
                $query->whereHas('panne', function($q) use ($ecoleId) {
                    $q->whereHas('site', fn($siteQ) => $siteQ->where('ecole_id', $ecoleId));
                });
            }

            $query->orderBy('heure_rdv', 'asc');
            $data = $perPage ? $query->paginate($perPage) : $query->get();

            return $this->successResponse('Interventions du jour récupérées.', $data);
        } catch (Exception $e) {
            Log::error("Error in DashboardService::getInterventionsDuJour - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getInterventionsAVenir(?int $perPage): JsonResponse
    {
        try {
            $query = Intervention::with(['panne.site', 'techniciens', 'ordreMission'])
                ->where('date_intervention', '>', now())
                ->where('statut', StatutIntervention::PLANIFIEE);

            // Auto-filtre école
            if ($this->isEcoleUser()) {
                $ecoleId = $this->getEcoleId();
                $query->whereHas('panne', function($q) use ($ecoleId) {
                    $q->whereHas('site', fn($siteQ) => $siteQ->where('ecole_id', $ecoleId));
                });
            }

            $query->orderBy('date_intervention', 'asc');
            $data = $perPage ? $query->paginate($perPage) : $query->get();

            return $this->successResponse('Interventions à venir récupérées.', $data);
        } catch (Exception $e) {
            Log::error("Error in DashboardService::getInterventionsAVenir - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getOrdresMissionDisponibles(?int $perPage): JsonResponse
    {
        try {
            $query = OrdreMission::with(['panne.site', 'ville', 'interventions'])
                ->where('candidature_cloturee', false)
                ->where('statut', 'en_attente')
                ->where(function($q) {
                    $q->whereNull('date_fin_candidature')
                      ->orWhere('date_fin_candidature', '>=', now());
                });

            $query->orderBy('date_generation', 'desc');
            $data = $perPage ? $query->paginate($perPage) : $query->get();

            return $this->successResponse('Ordres de mission disponibles récupérés.', $data);
        } catch (Exception $e) {
            Log::error("Error in DashboardService::getOrdresMissionDisponibles - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
