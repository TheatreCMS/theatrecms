<?php

namespace TheatreCMS\Tests\Unit;

use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManager;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Models\ExternalReference;
use TheatreCMS\Repositories\ExternalReferenceRepository;
use TheatreCMS\Tests\Includes\UsesSqliteEntityManager;

class ExternalReferenceRepositoryTest extends TestCase
{
    use UsesSqliteEntityManager;

    private EntityManager $em;
    private ExternalReferenceRepository $references;

    protected function setUp(): void
    {
        $this->em = $this->createSqliteEntityManager();
        $this->references = new ExternalReferenceRepository($this->em);
    }

    public function testLinkCreatesAReferenceFoundFromEitherSide(): void
    {
        $syncedAt = new DateTimeImmutable('2026-10-01 09:00:00');
        $created = $this->references->link('wp', 'post', '1234', 'production', 7, $syncedAt);
        $this->em->clear();

        $found = $this->references->find('wp', 'post', '1234');
        $this->assertInstanceOf(ExternalReference::class, $found);
        $this->assertSame($created->getId(), $found->getId());
        $this->assertSame('production', $found->getContentType());
        $this->assertSame(7, $found->getContentId());
        $this->assertEquals($syncedAt, $found->getSyncedAt());

        $this->assertSame(
            [['wp', 'post', '1234']],
            $this->keys($this->references->forContent('production', 7)),
        );
    }

    public function testLookupsDistinguishSourceTypeAndId(): void
    {
        $this->references->link('wp', 'post', '1', 'production', 7);

        $this->assertNull($this->references->find('wp', 'post', '2'));
        $this->assertNull($this->references->find('wp', 'attachment', '1'));
        $this->assertNull($this->references->find('tessitura', 'post', '1'));
        $this->assertSame([], $this->references->forContent('production', 8));
        $this->assertSame([], $this->references->forContent('person', 7));
    }

    public function testLinkingAgainRepointsTheSameReference(): void
    {
        $first = $this->references->link('wp', 'post', '1234', 'production', 7, new DateTimeImmutable('2026-10-01 09:00'));
        $later = new DateTimeImmutable('2026-10-02 09:00');

        $second = $this->references->link('wp', 'post', '1234', 'production', 9, $later);

        $this->assertSame($first->getId(), $second->getId());
        $this->assertSame(9, $second->getContentId());
        $this->assertEquals($later, $second->getSyncedAt());
        $this->assertSame([], $this->references->forContent('production', 7));
        $this->assertCount(1, $this->em->getRepository(ExternalReference::class)->findAll());
    }

    public function testContentCanHaveReferencesFromSeveralSources(): void
    {
        $this->references->link('wp', 'post', '1234', 'production', 7);
        $this->references->link('tessitura', 'production_season', '5501', 'production', 7);
        $this->references->link('tessitura', 'production_season', '5502', 'production', 7);

        $this->assertSame([
            ['tessitura', 'production_season', '5501'],
            ['tessitura', 'production_season', '5502'],
            ['wp', 'post', '1234'],
        ], $this->keys($this->references->forContent('production', 7)));
    }

    public function testTouchUpdatesOnlyTheSyncTime(): void
    {
        $this->references->link('tessitura', 'facility', '12', 'venue', 3, new DateTimeImmutable('2026-10-01 09:00'));
        $at = new DateTimeImmutable('2026-10-03 12:30');

        $touched = $this->references->touch('tessitura', 'facility', '12', $at);

        $this->assertEquals($at, $touched->getSyncedAt());
        $this->assertSame('venue', $touched->getContentType());
        $this->assertSame(3, $touched->getContentId());
    }

    public function testTouchingAMissingReferenceThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->references->touch('tessitura', 'facility', '404');
    }

    public function testDeleteForContentRemovesOnlyThatContentsReferences(): void
    {
        $this->references->link('wp', 'post', '1', 'production', 7);
        $this->references->link('tessitura', 'production_season', '5501', 'production', 7);
        $this->references->link('wp', 'post', '2', 'production', 8);

        $this->assertSame(2, $this->references->deleteForContent('production', 7));

        $this->assertSame([], $this->references->forContent('production', 7));
        $this->assertNull($this->references->find('wp', 'post', '1'));
        $this->assertNotNull($this->references->find('wp', 'post', '2'));
        $this->assertSame(0, $this->references->deleteForContent('production', 7));
    }

    public function testTheDatabaseRejectsADuplicateExternalRecord(): void
    {
        $this->references->link('wp', 'post', '1234', 'production', 7);
        $this->em->persist(new ExternalReference('wp', 'post', '1234', 'page', 1));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->em->flush();
    }

    /**
     * @param ExternalReference[] $references
     * @return array<int, array{string, string, string}>
     */
    private function keys(array $references): array
    {
        return array_map(
            static fn(ExternalReference $r): array => [$r->getSource(), $r->getSourceType(), $r->getSourceId()],
            $references,
        );
    }
}
