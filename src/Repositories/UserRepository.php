<?php

namespace Clubdeuce\TheatreCMS\Repositories;

use Clubdeuce\TheatreCMS\Models\User;

class UserRepository extends BaseRepository
{
    protected string $entityClass = User::class;

    public function create(array $args): User
    {
        $user = new User($args['email']);
        $user->setPasswordHash(password_hash($args['password'], PASSWORD_DEFAULT));
        $user->setRegisteredDtm(new \DateTimeImmutable());

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    public function findByEmail(string $email): ?object
    {
        return $this->em->getRepository($this->entityClass)->findOneBy(['email' => $email]);
    }
}
