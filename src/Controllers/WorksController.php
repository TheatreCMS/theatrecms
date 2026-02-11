<?php

namespace Clubdeuce\TheatreCMS\Controllers;

use Clubdeuce\TheatreCMS\Repositories\WorkRepository;

class WorksController extends BaseController
{
    public function __construct(WorkRepository $repository)
    {
        $this->repository = $repository;
    }
}
