<?php

namespace Clubdeuce\TheatreCMS\Repositories;

use Clubdeuce\TheatreCMS\Models\User;

class UserRepository extends Base
{
    protected string $entityClass = User::class;

    public function create(array $args): User
    {
        $args = array_merge($args, [
            'email' => null,
        ]);

        $user = new User($args['email']);
        $user->setPasswordHash($args['password']);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
