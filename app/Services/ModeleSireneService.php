<?php

namespace App\Services;

use App\Repositories\Contracts\ModeleSireneRepositoryInterface;
use App\Services\Contracts\ModeleSireneServiceInterface;

class ModeleSireneService extends BaseService implements ModeleSireneServiceInterface
{
    public function __construct(ModeleSireneRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }
}
