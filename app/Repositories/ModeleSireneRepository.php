<?php

namespace App\Repositories;

use App\Models\ModeleSirene;
use App\Repositories\Contracts\ModeleSireneRepositoryInterface;

class ModeleSireneRepository extends BaseRepository implements ModeleSireneRepositoryInterface
{
    public function __construct(ModeleSirene $model)
    {
        parent::__construct($model);
    }
}
