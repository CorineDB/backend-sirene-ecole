<?php

namespace App\Services;

use App\Repositories\Contracts\VilleRepositoryInterface;
use App\Services\Contracts\VilleServiceInterface;

class VilleService extends BaseService implements VilleServiceInterface
{
    public function __construct(VilleRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }
}
