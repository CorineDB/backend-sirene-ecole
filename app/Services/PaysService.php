<?php

namespace App\Services;

use App\Repositories\Contracts\PaysRepositoryInterface;
use App\Services\Contracts\PaysServiceInterface;

class PaysService extends BaseService implements PaysServiceInterface
{
    public function __construct(PaysRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }
}
