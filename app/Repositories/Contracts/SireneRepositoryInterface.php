<?php

namespace App\Repositories\Contracts;

interface SireneRepositoryInterface extends BaseRepositoryInterface
{
    public function findByNumeroSerie(string $numeroSerie, array $relations = []);
    public function getSirenesDisponibles(array $relations = []);
    public function affecterSireneASite(string $sireneId, string $siteId, ?string $ecoleId);
    public function getSirenesAvecAbonnementActif(array $relations = []);
}

