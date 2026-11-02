<?php

declare(strict_types=1);

namespace TheatreCMS\Tests\Integration;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Models\Production;
use TheatreCMS\Models\Season;
use TheatreCMS\Repositories\ContentMetaRepository;
use TheatreCMS\Repositories\ProductionRepository;
use TheatreCMS\Repositories\SeasonRepository;
use TheatreCMS\Tests\Includes\UsesSqliteEntityManager;

/**
 * Production slugs are unique within a season, so two seasons can stage the same work under
 * the same slug.
 *
 * @coversDefaultClass \TheatreCMS\Repositories\ProductionRepository
 */
class ProductionRepositoryTest extends TestCase
{
    use UsesSqliteEntityManager;

    private EntityManager $em;
    private ProductionRepository $productions;

    protected function setUp(): void
    {
        $this->em = $this->createSqliteEntityManager();
        $this->productions = new ProductionRepository($this->em, new ContentMetaRepository($this->em));
    }

    public function testTheSameWorkInTwoSeasonsKeepsOneSlug(): void
    {
        $season2005 = $this->season('2005');
        $season2010 = $this->season('2010');

        $first = $this->production('The Glass Menagerie', $season2005);
        $second = $this->production('The Glass Menagerie', $season2010);

        $this->assertSame('the-glass-menagerie', $first->getSlug());
        $this->assertSame('the-glass-menagerie', $second->getSlug());
        $this->assertSame($first, $this->productions->fetchPublicBySeasonAndSlug($season2005, 'the-glass-menagerie'));
        $this->assertSame($second, $this->productions->fetchPublicBySeasonAndSlug($season2010, 'the-glass-menagerie'));
    }

    public function testSlugsStayUniqueWithinASeason(): void
    {
        $season = $this->season('2005');
        $this->production('The Glass Menagerie', $season);

        $this->assertSame('the-glass-menagerie-1', $this->production('The Glass Menagerie', $season)->getSlug());
    }

    public function testMovingToASeasonThatUsesTheSlugRenamesIt(): void
    {
        $season2005 = $this->season('2005');
        $season2010 = $this->season('2010');
        $this->production('The Glass Menagerie', $season2010);
        $moving = $this->production('The Glass Menagerie', $season2005);

        $this->productions->moveToSeason($moving, $season2010);

        $this->assertSame($season2010, $moving->getSeason());
        $this->assertSame('the-glass-menagerie-1', $moving->getSlug());
    }

    private function season(string $year): Season
    {
        return (new SeasonRepository($this->em, new ContentMetaRepository($this->em)))->create([
            'label' => $year,
            'startDate' => "$year-09-01",
            'endDate' => ($year + 1) . '-06-01',
        ]);
    }

    private function production(string $name, Season $season): Production
    {
        return $this->productions->create(['name' => $name, 'seasonId' => $season->getId()]);
    }
}
