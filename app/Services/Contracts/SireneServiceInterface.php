<?php

namespace App\Services\Contracts;

use Illuminate\Http\JsonResponse;

interface SireneServiceInterface extends BaseServiceInterface
{
    public function findByNumeroSerie(string $numeroSerie, array $relations = []): JsonResponse;
    public function getSirenesDisponibles(array $relations = []): JsonResponse;
    public function affecterSireneASite(string $sireneId, string $siteId, ?string $ecoleId = null): JsonResponse;
    public function getProgrammationByNumeroSerie(string $numeroSerie): JsonResponse;
}
