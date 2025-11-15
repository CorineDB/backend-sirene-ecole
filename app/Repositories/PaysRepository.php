<?php

namespace App\Repositories;

use App\Models\Pays;
use App\Repositories\Contracts\PaysRepositoryInterface;

class PaysRepository extends BaseRepository implements PaysRepositoryInterface
{
    public function __construct(Pays $model)
    {
        parent::__construct($model);
    }
}
