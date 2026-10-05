<?php

namespace TheatreCMS\Tests\Unit;

use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Enums\ContentStatus;
use TheatreCMS\Models;
use TheatreCMS\Repositories\WorkRepository;
use TheatreCMS\Tests\Includes\UsesSqliteEntityManager;

/**
 * Every content model records created_at and modified_at, kept current by the lifecycle callbacks in
 * HasCreatedTimestamp and HasModifiedTimestamp (THE-136).
 */
class ContentTimestampsTest extends TestCase
{
    use UsesSqliteEntityManager;

    /**
     * @return array<string, array{class-string}>
     */
    public static function timestampedModels(): array
    {
        $models = [
            Models\ContentMeta::class, Models\Event::class, Models\Media::class, Models\Menu::class,
            Models\MenuItem::class, Models\Page::class, Models\Person::class, Models\Post::class,
            Models\Production::class, Models\Season::class, Models\Sponsor::class, Models\Term::class,
            Models\Venue::class, Models\Work::class,
        ];

        return array_combine(
            array_map(static fn(string $class): string => substr(strrchr($class, '\\'), 1), $models),
            array_map(static fn(string $class): array => [$class], $models),
        );
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('timestampedModels')]
    public function testModelMapsBothColumnsAndTheCallbacks(string $class): void
    {
        $metadata = $this->createSqliteEntityManager(createSchema: false)->getClassMetadata($class);

        $this->assertSame('created_at', $metadata->getColumnName('createdAt'));
        $this->assertSame('modified_at', $metadata->getColumnName('modifiedAt'));
        $this->assertFalse($metadata->isNullable('createdAt'));
        $this->assertFalse($metadata->isNullable('modifiedAt'));
        $this->assertContains('initializeCreatedTimestamp', $metadata->getLifecycleCallbacks(Events::prePersist));
        $this->assertContains('initializeModifiedTimestamp', $metadata->getLifecycleCallbacks(Events::prePersist));
        $this->assertContains('refreshModifiedTimestamp', $metadata->getLifecycleCallbacks(Events::preUpdate));
    }

    public function testPersistingSetsBothTimestamps(): void
    {
        $em = $this->createSqliteEntityManager();
        $before = new DateTimeImmutable();

        $work = (new WorkRepository($em))->create(['title' => 'Symphony No. 5']);

        $this->assertInstanceOf(Models\Work::class, $work);
        $this->assertGreaterThanOrEqual($before, $work->getCreatedAt());
        $this->assertGreaterThanOrEqual($before, $work->getModifiedAt());
    }

    public function testChangingAnEntityRefreshesModifiedAtButNotCreatedAt(): void
    {
        $em = $this->createSqliteEntityManager();
        $work = (new WorkRepository($em))->create(['title' => 'Symphony No. 5']);
        $id = $work->getId();
        $this->backdate($em, 'works', $id);
        $em->clear();

        $work = $em->find(Models\Work::class, $id);
        $work->setTitle('Symphony No. 5 in C minor');
        $em->flush();
        $em->clear();

        $reloaded = $em->find(Models\Work::class, $id);
        $this->assertSame('2000-01-01', $reloaded->getCreatedAt()->format('Y-m-d'), 'created_at is never changed');
        $this->assertGreaterThan(new DateTimeImmutable('2001-01-01'), $reloaded->getModifiedAt(), 'modified_at is refreshed and saved');
    }

    public function testFlushingWithoutChangesLeavesModifiedAtAlone(): void
    {
        $em = $this->createSqliteEntityManager();
        $work = (new WorkRepository($em))->create(['title' => 'Symphony No. 5']);
        $id = $work->getId();
        $this->backdate($em, 'works', $id);
        $em->clear();

        $em->find(Models\Work::class, $id);
        $em->flush();
        $em->clear();

        $this->assertSame('2000-01-01', $em->find(Models\Work::class, $id)->getModifiedAt()->format('Y-m-d'));
    }

    public function testRefreshesAModelThatAlsoSetsThemInItsConstructor(): void
    {
        $em = $this->createSqliteEntityManager();
        $post = new Models\Post('Hello', ContentStatus::DRAFT, '');
        $post->setSlug('hello');
        $em->persist($post);
        $em->flush();
        $id = $post->getId();
        $this->backdate($em, 'posts', $id);
        $em->clear();

        $em->find(Models\Post::class, $id)->setTitle('Hello again');
        $em->flush();
        $em->clear();

        $reloaded = $em->find(Models\Post::class, $id);
        $this->assertSame('2000-01-01', $reloaded->getCreatedAt()->format('Y-m-d'));
        $this->assertGreaterThan(new DateTimeImmutable('2001-01-01'), $reloaded->getModifiedAt());
    }

    private function backdate(EntityManager $em, string $table, int $id): void
    {
        $em->getConnection()->executeStatement(
            "UPDATE {$table} SET created_at = '2000-01-01 00:00:00', modified_at = '2000-01-01 00:00:00' WHERE id = ?",
            [$id],
        );
    }
}
