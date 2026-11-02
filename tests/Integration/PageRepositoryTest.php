<?php

declare(strict_types=1);

namespace TheatreCMS\Tests\Integration;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManager;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Enums\ContentStatus;
use TheatreCMS\Models\Page;
use TheatreCMS\Repositories\PageRepository;
use TheatreCMS\Tests\Includes\UsesSqliteEntityManager;

/**
 * Pages nest: they're found by path, their slugs are unique among siblings, and deleting one
 * moves its children up.
 *
 * @coversDefaultClass \TheatreCMS\Repositories\PageRepository
 */
class PageRepositoryTest extends TestCase
{
    use UsesSqliteEntityManager;

    private EntityManager $em;
    private PageRepository $pages;

    protected function setUp(): void
    {
        $this->em = $this->createSqliteEntityManager();
        $this->pages = new PageRepository($this->em);
    }

    public function testFetchPublicByPathFindsANestedPage(): void
    {
        $about = $this->page('About');
        $board = $this->page('Board of Directors', $about);

        $this->assertSame($about, $this->pages->fetchPublicByPath('about'));
        $this->assertSame($board, $this->pages->fetchPublicByPath('about/board-of-directors'));
    }

    public function testFetchPublicByPathIsNullForAPageUnderAnotherParent(): void
    {
        $about = $this->page('About');
        $this->page('Contact');
        $this->page('Board of Directors', $about);

        $this->assertNull($this->pages->fetchPublicByPath('contact/board-of-directors'));
        $this->assertNull($this->pages->fetchPublicByPath('board-of-directors'));
    }

    public function testAnUnpublishedAncestorHidesItsDescendants(): void
    {
        $about = $this->page('About', status: 'draft');
        $this->page('Board of Directors', $about);

        $this->assertNull($this->pages->fetchPublicByPath('about/board-of-directors'));
    }

    public function testSlugsAreUniqueAmongSiblingsOnly(): void
    {
        $about = $this->page('About');
        $contact = $this->page('Contact');

        $this->assertSame('team', $this->page('Team', $about)->getSlug());
        $this->assertSame('team', $this->page('Team', $contact)->getSlug());
        $this->assertSame('team', $this->page('Team')->getSlug());
        $this->assertSame('team-1', $this->page('Team')->getSlug());
    }

    public function testDatabaseRejectsDuplicateTopLevelSlugs(): void
    {
        $this->page('About');

        $duplicate = new Page('Another About', ContentStatus::PUBLISHED, '{"blocks":[]}');
        $duplicate->setSlug('about');
        $this->em->persist($duplicate);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->em->flush();
    }

    public function testMoveToRenamesTheSlugWhenANewSiblingUsesIt(): void
    {
        $about = $this->page('About');
        $this->page('Team', $about);
        $team = $this->page('Team');

        $this->pages->moveTo($team, $about);

        $this->assertSame($about, $team->getParent());
        $this->assertSame('team-1', $team->getSlug());
    }

    public function testMoveToRejectsADescendant(): void
    {
        $about = $this->page('About');
        $board = $this->page('Board of Directors', $about);

        $this->expectException(InvalidArgumentException::class);

        $this->pages->moveTo($about, $board);
    }

    public function testDeletingAPageMovesItsChildrenUpToItsParent(): void
    {
        $about = $this->page('About');
        $board = $this->page('Board of Directors', $about);
        $chair = $this->page('Chair', $board);

        $this->pages->delete($board);

        $this->assertSame($about, $chair->getParent());
        $this->assertSame($chair, $this->pages->fetchPublicByPath('about/chair'));
    }

    public function testPossibleParentsLeaveOutThePageAndItsDescendants(): void
    {
        $about = $this->page('About');
        $board = $this->page('Board of Directors', $about);
        $this->page('Chair', $board);
        $contact = $this->page('Contact');

        $this->assertSame([$contact], $this->pages->fetchPossibleParents($about));
    }

    private function page(string $title, ?Page $parent = null, string $status = 'published'): Page
    {
        return $this->pages->create([
            'title' => $title,
            'content' => '{"blocks":[]}',
            'status' => $status,
            'parentId' => $parent?->getId(),
        ]);
    }
}
