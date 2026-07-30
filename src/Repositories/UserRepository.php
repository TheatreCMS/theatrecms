<?php

namespace TheatreCMS\Repositories;

use TheatreCMS\Models\User;
use Delight\Auth\Auth;
use Delight\Auth\Role;


class UserRepository extends BaseRepository
{
    protected string $entityClass = User::class;

    protected Auth $auth;

    public function setAuth(Auth $auth): void
    {
        $this->auth = $auth;
    }

    public function create(array $args): int
    {
        if (!isset($this->auth)) {
            throw new \RuntimeException('Auth service has not been configured for UserRepository.');
        }

        $userId = $this->auth->admin()->createUserWithUniqueUsername(
            (string) $args['email'],
            (string) $args['password'],
            (string) $args['username']
        );

        $this->syncRoleByUserId($userId, (string) ($args['role'] ?? 'user'));

        return $userId;
    }

    public function findByEmail(string $email): ?object
    {
        return $this->em->getRepository($this->entityClass)->findOneBy(['email' => $email]);
    }

    public function findByUsername(string $username): ?object
    {
        return $this->em->getRepository($this->entityClass)->findOneBy(['username' => $username]);
    }

    public function updatePassword(int $userId, string $password): void
    {
        if (!isset($this->auth)) {
            throw new \RuntimeException('Auth service has not been configured for UserRepository.');
        }

        $this->auth->admin()->changePasswordForUserById($userId, $password);
    }

    public function syncRoleByUserId(int $userId, string $role): void
    {
        if (!isset($this->auth)) {
            throw new \RuntimeException('Auth service has not been configured for UserRepository.');
        }

        $user = $this->fetch($userId);
        if (!$user instanceof User) {
            throw new \InvalidArgumentException('Unknown user ID provided.');
        }

        $isAdmin = $this->isAdmin($user);
        $targetIsAdmin = $role === 'admin';

        if ($targetIsAdmin && !$isAdmin) {
            $this->auth->admin()->addRoleForUserById($userId, Role::ADMIN);
            return;
        }

        if (!$targetIsAdmin && $isAdmin) {
            $this->auth->admin()->removeRoleForUserById($userId, Role::ADMIN);
        }
    }

    public function resolveRoleLabel(User $user): string
    {
        return $this->isAdmin($user) ? 'admin' : 'user';
    }

    private function isAdmin(User $user): bool
    {
        return (($user->getRolesMask() & Role::ADMIN) === Role::ADMIN);
    }
}
