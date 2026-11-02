<?php

declare(strict_types=1);

namespace TheatreCMS\Tests\Integration;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Exceptions\SlugAllocationException;
use TheatreCMS\Repositories\ContentMetaRepository;
use TheatreCMS\Repositories\PostRepository;
use TheatreCMS\Tests\Includes\UsesSqliteEntityManager;

class PostRepositoryTest extends TestCase
{
    use UsesSqliteEntityManager;

    private EntityManager $em;
    private ContentMetaRepository $contentMeta;

    protected function setUp(): void
    {
        $this->em = $this->createSqliteEntityManager();
        $this->contentMeta = new ContentMetaRepository($this->em);
    }

    public function testCreateAddsNumericSuffixesForSlugCollisions(): void
    {
        $posts = new PostRepository($this->em, $this->contentMeta);

        $this->assertSame('opening-night', $this->create($posts)->getSlug());
        $this->assertSame('opening-night-1', $this->create($posts)->getSlug());
        $this->assertSame('opening-night-2', $this->create($posts)->getSlug());
    }

    public function testCreateTranslatesResidualDatabaseCollisions(): void
    {
        $posts = new class ($this->em, $this->contentMeta) extends PostRepository {
            protected function slugExists(string $slug, array $scope = []): bool
            {
                return false;
            }
        };
        $this->create($posts);

        $this->expectException(SlugAllocationException::class);
        $this->expectExceptionMessage('Unable to allocate a unique URL for the post. Please try again.');

        $this->create($posts);
    }

    private function create(PostRepository $posts): \TheatreCMS\Models\Post
    {
        return $posts->create([
            'title' => 'Opening Night',
            'content' => '{"blocks":[]}',
        ]);
    }
}
