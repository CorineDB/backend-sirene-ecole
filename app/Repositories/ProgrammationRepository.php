<?php

namespace App\Repositories;

use App\Models\Programmation;
use App\Repositories\Contracts\ProgrammationRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

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
}
