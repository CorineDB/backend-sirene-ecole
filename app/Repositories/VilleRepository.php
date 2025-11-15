<?php

namespace App\Repositories;

use App\Models\Ville;
use App\Repositories\Contracts\VilleRepositoryInterface;

class VilleRepository extends BaseRepository implements VilleRepositoryInterface
{
    public function __construct(Ville $model)
    {
        parent::__construct($model);
    }
}
