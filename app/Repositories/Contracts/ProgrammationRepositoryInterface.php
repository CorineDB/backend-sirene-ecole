<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProgrammationRepositoryInterface
{
    /**
     * @param int $sireneId
     * @return Collection
     */
    public function getBySireneId(string $sireneId): Collection;

    /**
     * Get paginated programmations for a sirene
     *
     * @param string $sireneId
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginatedBySireneId(string $sireneId, int $perPage = 15): LengthAwarePaginator;
}
