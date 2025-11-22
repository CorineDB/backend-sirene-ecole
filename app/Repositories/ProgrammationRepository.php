<?php

namespace App\Repositories;

use App\Models\Programmation;
use App\Repositories\Contracts\ProgrammationRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProgrammationRepository extends BaseRepository implements ProgrammationRepositoryInterface
{
    /**
     * @param Programmation $model
     */
    public function __construct(Programmation $model)
    {
        parent::__construct($model);
    }

    /**
     * @param int $sireneId
     * @return Collection
     */
    public function getBySireneId(string $sireneId): Collection
    {
        return $this->model->where('sirene_id', $sireneId)->get();
    }

    /**
     * Get paginated programmations for a sirene
     *
     * @param string $sireneId
     * @param int $perPage Number of items per page (default: 15)
     * @return LengthAwarePaginator
     */
    public function getPaginatedBySireneId(string $sireneId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model
            ->where('sirene_id', $sireneId)
            ->with(['ecole', 'site', 'sirene', 'abonnement', 'calendrier', 'creePar'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }
}
