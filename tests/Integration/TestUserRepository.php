<?php

namespace TheatreCMS\Tests\Integration;

use TheatreCMS\Models\User;
use TheatreCMS\Repositories\UserRepository;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;

class TestUserRepository extends TestCase
{
    private EntityManager $em;

    protected function setUp(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers())) {
            $this->markTestSkipped('PDO SQLite driver is not available; skipping integration test.');
        }

        $paths = [__DIR__ . '/../../src/Models'];
        $isDevMode = true;

        $config = ORMSetup::createAttributeMetadataConfiguration($paths, $isDevMode);

        $connectionParams = [
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ];

        $connection = DriverManager::getConnection($connectionParams);

        $this->em = new EntityManager($connection, $config);

        $schemaTool = new SchemaTool($this->em);
        $classes = [$this->em->getClassMetadata(User::class)];
        $schemaTool->createSchema($classes);
    }

    public function testCreateAndFindByEmail(): void
    {
        $repo = new UserRepository($this->em);
        $email = 'test@example.com';
        $password = 'password123';

        $repo->create([
            'email' => $email,
            'password' => $password,
        ]);

        $foundUser = $repo->findByEmail($email);

        $this->assertNotNull($foundUser);
        $this->assertEquals($email, $foundUser->getEmail());
        $this->assertTrue(password_verify($password, $foundUser->getPasswordHash()));

        $notFound = $repo->findByEmail('nonexistent@example.com');
        $this->assertNull($notFound);
    }
}
