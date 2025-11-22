<?php

namespace App\Repositories;

use App\Enums\StatutSirene;
use App\Models\Sirene;
use App\Repositories\Contracts\SireneRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class SireneRepository extends BaseRepository implements SireneRepositoryInterface
{
    public function __construct(Sirene $model)
    {
        parent::__construct($model);
    }

    /**
     * Override find to always load active abonnement with tokenActif
     */
    public function find(string $id, array $columns = ['*'], array $relations = []): ?Model
    {
        return $this->model->with($relations)
            ->with(['abonnementActif.tokenActif'])
            ->findOrFail($id, $columns);
    }

    /**
     * Override paginate to always load active abonnement with tokenActif
     */
    public function paginate(int $perPage = 15, array $columns = ['*'], array $relations = []): LengthAwarePaginator
    {
        return $this->model->with($relations)
            ->with(['abonnementActif.tokenActif'])
            ->orderBy("created_at", "desc")
            ->paginate($perPage);
    }

    public function findByNumeroSerie(string $numeroSerie, array $relations = [])
    {
        return $this->model->with($relations)
            ->with(['abonnementActif.tokenActif'])
            ->where('numero_serie', $numeroSerie)
            ->first();
    }

    public function getSirenesDisponibles(array $relations = []): Collection
    {
        return $this->model->with($relations)
            ->with(['abonnementActif.tokenActif'])
            ->where('statut', StatutSirene::EN_STOCK->value)
            ->orWhere('old_statut', StatutSirene::EN_STOCK->value)
            ->whereNull('site_id')
            ->get();
    }

    public function affecterSireneASite(string $sireneId, string $siteId, ?string $ecoleId)
    {
        return $this->update($sireneId, [
            'site_id' => $siteId,
            'ecole_id' => $ecoleId,
            'statut' => StatutSirene::RESERVE,
            'date_installation' => now(),
        ]);
    }

    /**
     * Récupérer toutes les sirènes dont l'école a un abonnement actif
     *
     * @param array $relations
     * @param int $perPage
     * @param string|null $ecoleId
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getSirenesAvecAbonnementActif(array $relations = [], int $perPage = 15, ?string $ecoleId = null)
    {
        $query = $this->model->with($relations)
            ->with(['abonnementActif.tokenActif'])
            ->whereHas('ecole', function ($query) {
                $query->whereHas('abonnements', function ($subQuery) {
                    $subQuery->where('statut', \App\Enums\StatutAbonnement::ACTIF->value)
                        ->where('date_debut', '<=', now())
                        ->where('date_fin', '>=', now());
                });
            });

        // Filtre optionnel par ecole_id
        if ($ecoleId) {
            $query->where('ecole_id', $ecoleId);
        }

        return $query->paginate($perPage);
    }
}
