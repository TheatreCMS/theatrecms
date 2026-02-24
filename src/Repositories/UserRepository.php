<?php

namespace Clubdeuce\TheatreCMS\Repositories;

use Clubdeuce\TheatreCMS\Models\User;
use Delight\Auth\Auth;
use Delight\Auth\InvalidEmailException;
use Delight\Auth\InvalidPasswordException;
use Delight\Auth\TooManyRequestsException;
use Delight\Auth\UserAlreadyExistsException;
use Delight\Auth\AuthError;


class UserRepository extends BaseRepository
{
    protected string $entityClass = User::class;

    protected Auth $auth;

    public function setAuth(Auth $auth): void
    {
        $this->auth = $auth;
    }

    public function create(array $args): void
    {
        try {
            $this->auth->register($args['email'], $args['password'], $args['username']);
        } catch (InvalidEmailException|AuthError|InvalidPasswordException|UserAlreadyExistsException|TooManyRequestsException $e) {
            trigger_error('Failed to create user: ' . $e->getMessage());
        }
    }

    public function findByEmail(string $email): ?object
    {
        return $this->em->getRepository($this->entityClass)->findOneBy(['email' => $email]);
    }

    public function findByUsername(string $username): ?object
    {
        return $this->em->getRepository($this->entityClass)->findOneBy(['username' => $username]);
    }
}
