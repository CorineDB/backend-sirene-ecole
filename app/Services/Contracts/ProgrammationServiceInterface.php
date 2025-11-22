<?php

namespace App\Services\Contracts;

use App\Models\Programmation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;

interface ProgrammationServiceInterface
{

    /**
     * @param string $sireneId
     * @return JsonResponse
     */
    public function getBySireneId(string $sireneId): JsonResponse;

    /**
     * Get paginated programmations for a sirene
     *
     * @param string $sireneId
     * @param int $perPage
     * @return JsonResponse
     */
    public function getPaginatedBySireneId(string $sireneId, int $perPage = 15): JsonResponse;

    /**
     * Get effective programmations for a sirene on a specific date, considering holidays.
     *
     * @param string $sireneId
     * @param string $date (format Y-m-d)
     * @return JsonResponse
     */
    public function getEffectiveProgrammationsForSirene(string $sireneId, string $date): JsonResponse;
}
