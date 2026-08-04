<?php

namespace TheatreCMS\Tests\Integration;

use Delight\Auth\Auth;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Models\User;
use TheatreCMS\Repositories\UserRepository;

class TestUserRepository extends TestCase
{
    private Connection $connection;
    private Auth $auth;
    private UserRepository $repository;

    protected function setUp(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers())) {
            $this->markTestSkipped('PDO SQLite driver is not available; skipping integration test.');
        }

        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $pdo = $this->connection->getNativeConnection();
        $schema = file_get_contents(dirname(__DIR__, 2) . '/vendor/delight-im/auth/Database/SQLite.sql');
        foreach (array_filter(array_map('trim', explode(';', (string) $schema))) as $statement) {
            $pdo->exec($statement);
        }

        $this->auth = new Auth($pdo);
        $this->repository = new UserRepository($this->connection, $this->auth);
    }

    public function testCreatesAndFindsUserThroughDelightSchema(): void
    {
        $userId = $this->repository->create([
            'email' => 'test@example.com',
            'username' => 'test-user',
            'password' => 'password123',
        ]);

        $user = $this->repository->findByEmail('test@example.com');

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame($userId, $user->getId());
        $this->assertSame('test-user', $user->getUsername());
        $this->assertSame($userId, $this->repository->findByUsername('test-user')?->getId());
        $this->assertNull($this->repository->findByEmail('missing@example.com'));
    }

    public function testPaginatesUsersWithoutDoctrineMetadata(): void
    {
        for ($index = 1; $index <= 3; $index++) {
            $this->repository->create([
                'email' => sprintf('user%d@example.com', $index),
                'username' => sprintf('user%d', $index),
                'password' => 'password123',
            ]);
        }

        $page = $this->repository->fetchPage(2, 2);

        $this->assertSame(3, $page['total']);
        $this->assertSame(2, $page['page']);
        $this->assertSame(2, $page['perPage']);
        $this->assertCount(1, $page['items']);
        $this->assertSame('user3@example.com', $page['items'][0]->getEmail());
        $this->assertCount(3, $this->repository->fetchAll());
    }

    public function testUpdatesEmailAndRoles(): void
    {
        $userId = $this->repository->create([
            'email' => 'old@example.com',
            'username' => 'admin-user',
            'password' => 'password123',
        ]);

        $this->repository->updateEmail($userId, 'new@example.com');
        $this->repository->syncRoleByUserId($userId, 'admin');

        $user = $this->repository->fetch($userId);

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('new@example.com', $user->getEmail());
        $this->assertSame('admin', $this->repository->resolveRoleLabel($user));
        $this->assertTrue($this->repository->hasAdminUser());
        $this->assertContains('ADMIN', $this->auth->admin()->getRolesForUserById($userId));
    }

    public function testDoctrineSchemaDoesNotContainUsersTable(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            [dirname(__DIR__, 2) . '/src/Models'],
            true
        );
        $entityManager = new EntityManager($this->connection, $config);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $sql = (new SchemaTool($entityManager))->getCreateSchemaSql($metadata);

        $this->assertNotContains(User::class, array_map(
            static fn($classMetadata): string => $classMetadata->getName(),
            $metadata
        ));
        $this->assertStringNotContainsString('CREATE TABLE users ', implode("\n", $sql));
    }
}
