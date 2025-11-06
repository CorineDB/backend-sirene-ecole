<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface ProgrammationRepositoryInterface
{
    /**
     * @param int $sireneId
     * @return Collection
     */
    public function getBySireneId(string $sireneId): Collection;
}
