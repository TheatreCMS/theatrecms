<?php

namespace TheatreCMS\Repositories;

use Delight\Auth\Auth;
use Delight\Auth\Role;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use TheatreCMS\Models\User;

class UserRepository implements PaginatedRepositoryInterface
{
    private const USER_COLUMNS = 'id, email, username, roles_mask, last_login';

    public function __construct(
        private readonly Connection $connection,
        private readonly Auth $auth
    ) {
    }

    /**
     * @param array<string, mixed> $args
     */
    public function create(array $args): int
    {
        $userId = $this->auth->admin()->createUserWithUniqueUsername(
            (string) $args['email'],
            (string) $args['password'],
            (string) $args['username']
        );

        $this->syncRoleByUserId($userId, (string) ($args['role'] ?? 'user'));

        return $userId;
    }

    public function fetch(int $id): ?User
    {
        $row = $this->connection->fetchAssociative(
            sprintf('SELECT %s FROM users WHERE id = ?', self::USER_COLUMNS),
            [$id]
        );

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * @return User[]
     */
    public function fetchAll(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            sprintf('SELECT %s FROM users ORDER BY id ASC', self::USER_COLUMNS)
        );

        return array_map($this->hydrate(...), $rows);
    }

    /**
     * @return array{items: User[], total: int, page: int, perPage: int}
     */
    public function fetchPage(int $page = 1, int $perPage = 25): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;
        $rows = $this->connection->fetchAllAssociative(
            sprintf(
                'SELECT %s FROM users ORDER BY id ASC LIMIT ? OFFSET ?',
                self::USER_COLUMNS
            ),
            [$perPage, $offset],
            [ParameterType::INTEGER, ParameterType::INTEGER]
        );

        return [
            'items' => array_map($this->hydrate(...), $rows),
            'total' => (int) $this->connection->fetchOne('SELECT COUNT(id) FROM users'),
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy('email', $email);
    }

    public function findByUsername(string $username): ?User
    {
        return $this->findOneBy('username', $username);
    }

    public function updateEmail(int $userId, string $email): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address provided.');
        }

        $updatedRows = $this->connection->executeStatement(
            'UPDATE users SET email = ? WHERE id = ?',
            [$email, $userId]
        );

        if ($updatedRows === 0 && $this->fetch($userId) === null) {
            throw new \InvalidArgumentException('Unknown user ID provided.');
        }
    }

    public function updatePassword(int $userId, string $password): void
    {
        $this->auth->admin()->changePasswordForUserById($userId, $password);
    }

    public function syncRoleByUserId(int $userId, string $role): void
    {
        $user = $this->fetch($userId);
        if ($user === null) {
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

    public function hasAdminUser(): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT EXISTS(SELECT 1 FROM users WHERE (roles_mask & ?) = ?)',
            [Role::ADMIN, Role::ADMIN],
            [ParameterType::INTEGER, ParameterType::INTEGER]
        );
    }

    private function findOneBy(string $column, string $value): ?User
    {
        $row = $this->connection->fetchAssociative(
            sprintf('SELECT %s FROM users WHERE %s = ?', self::USER_COLUMNS, $column),
            [$value]
        );

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): User
    {
        return new User(
            (int) $row['id'],
            (string) $row['email'],
            isset($row['username']) ? (string) $row['username'] : null,
            (int) $row['roles_mask'],
            isset($row['last_login']) ? (int) $row['last_login'] : null
        );
    }

    private function isAdmin(User $user): bool
    {
        return (($user->getRolesMask() & Role::ADMIN) === Role::ADMIN);
    }
}
