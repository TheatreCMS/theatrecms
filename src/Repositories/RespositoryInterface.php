<?php
namespace Clubdeuce\Theaterpress\Repositories;

interface RespositoryInterface
{
    /**
     * @return object[]
     */
    public function findAll(): array;
    public function findById(int $id): ?object;
}
