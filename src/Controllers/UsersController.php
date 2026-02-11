<?php

namespace Clubdeuce\TheatreCMS\Controllers;

use Clubdeuce\TheatreCMS\Repositories\UserRepository;


class UsersController extends BaseController
{
    public function __construct(UserRepository $repository)
    {
        $this->repository = $repository;
    }
}
