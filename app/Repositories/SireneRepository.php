<?php

namespace App\Repositories;

use App\Enums\StatutSirene;
use App\Models\Sirene;
use App\Repositories\Contracts\SireneRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class SireneRepository extends BaseRepository implements SireneRepositoryInterface
{
    public function __construct(Sirene $model)
    {
        parent::__construct($model);
    }

    public function findByNumeroSerie(string $numeroSerie, array $relations = [])
    {
        return $this->model->with($relations)
            ->where('numero_serie', $numeroSerie)
            ->first();
    }

    public function getSirenesDisponibles(array $relations = []): Collection
    {
        return $this->model->with($relations)
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
     * @return Collection
     */
    public function getSirenesAvecAbonnementActif(array $relations = []): Collection
    {
        return $this->model->with($relations)
            ->whereHas('ecole', function ($query) {
                $query->whereHas('abonnements', function ($subQuery) {
                    $subQuery->where('statut', \App\Enums\StatutAbonnement::ACTIF->value)
                        ->where('date_debut', '<=', now())
                        ->where('date_fin', '>=', now());
                });
            })
            ->get();
    }
}
