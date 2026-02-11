<?php

namespace Clubdeuce\TheatreCMS\Controllers;

use Clubdeuce\TheatreCMS\Repositories\SeasonRepository;

class SeasonController extends BaseController
{
    public function __construct(SeasonRepository $repository)
    {
        $this->repository = $repository;
    }
}
