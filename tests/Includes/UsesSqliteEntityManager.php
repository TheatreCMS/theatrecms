<?php

namespace TheatreCMS\Tests\Includes;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use TheatreCMS\Models\Media;
use TheatreCMS\Repositories\MediaRepository;

/**
 * Builds an EntityManager on an in-memory SQLite database with the full schema created from
 * the models in src/Models (or no tables, with $createSchema false). Skips the test when the
 * pdo_sqlite driver is unavailable.
 */
trait UsesSqliteEntityManager
{
    protected function createSqliteEntityManager(bool $createSchema = true): EntityManager
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('PDO SQLite driver is not available; skipping.');
        }

        $config = ORMSetup::createAttributeMetadataConfiguration([dirname(__DIR__, 2) . '/src/Models'], true);
        $config->enableNativeLazyObjects(true);
        $em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
        if ($createSchema) {
            (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
        }

        return $em;
    }

    /**
     * Persists a Media row; images get an image/jpeg MIME type, anything else a PDF one.
     */
    protected function createTestMedia(EntityManager $em, string $type = Media::TYPE_IMAGE): Media
    {
        return (new MediaRepository($em))->create([
            'url' => '/uploads/test-file',
            'filename' => 'test-file',
            'originalFilename' => 'test-file',
            'mimeType' => $type === Media::TYPE_IMAGE ? 'image/jpeg' : 'application/pdf',
            'sizeBytes' => 10,
            'mediaType' => $type,
        ]);
    }
}
