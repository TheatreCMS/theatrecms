<?php

declare(strict_types=1);

namespace TheatreCMS\Tests\Unit\Routing;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Slim\Factory\AppFactory;
use Slim\Interfaces\RouteInterface;
use TheatreCMS\ContentType\ContentTypeDefinition;
use TheatreCMS\Controllers\PageController;
use TheatreCMS\Controllers\ProductionController;
use TheatreCMS\Controllers\SeasonController;
use TheatreCMS\Routing\RouteRegistry;
use TheatreCMS\Taxonomy\TaxonomyDefinition;

class RouteRegistryTest extends TestCase
{
    public function testAContentTypesOwnRoutesDoNotCollide(): void
    {
        $registry = $this->registryWithSeasons();

        $registry->assertNoCollisions($this->routes(
            ['/admin/seasons', RouteRegistry::routeName('season', 'admin', 'index')],
            ['/admin/seasons/{id}', RouteRegistry::routeName('season', 'admin', 'show')],
            ['/seasons/{id}', RouteRegistry::routeName('season', 'public', 'single')],
        ));

        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unrelatedPatterns(): array
    {
        return [
            'sibling admin path' => ['/admin/seasons-report'],
            'sibling public path' => ['/seasons-archive'],
            'other admin area' => ['/admin/settings'],
            'catch-all' => ['/{slug}'],
        ];
    }

    #[DataProvider('unrelatedPatterns')]
    public function testRoutesOutsideTheTypesPrefixesDoNotCollide(string $pattern): void
    {
        $this->registryWithSeasons()->assertNoCollisions($this->routes([$pattern, null]));

        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function collidingRoutes(): array
    {
        return [
            'same admin prefix' => ['/admin/seasons', null],
            'under admin prefix' => ['/admin/seasons/report', null],
            'optional segment on admin prefix' => ['/admin/seasons[/{page}]', null],
            'same public prefix' => ['/seasons', null],
            'under public prefix' => ['/seasons/{slug}/{productionSlug}', null],
            'another type\'s route name' => ['/admin/seasons', RouteRegistry::routeName('production', 'admin', 'index')],
            'name sharing the type as a prefix' => ['/admin/seasons', 'content_type.season_extra.admin.index'],
        ];
    }

    #[DataProvider('collidingRoutes')]
    public function testRoutesTheTypeDoesNotOwnCollide(string $pattern, ?string $name): void
    {
        $registry = $this->registryWithSeasons();

        $this->expectException(RuntimeException::class);

        $registry->assertNoCollisions($this->routes([$pattern, $name]));
    }

    public function testANonPublicTypeDoesNotClaimItsPublicPrefix(): void
    {
        $registry = new RouteRegistry();
        $registry->registerRoutesForContentType(
            new ContentTypeDefinition('season', 'Seasons', 'Season', 'seasons', public: false)
        );

        $registry->assertNoCollisions($this->routes(['/seasons', null]));

        $this->addToAssertionCount(1);
    }

    public function testAControllersAdditionalPublicRoutesComeBeforeTheSingleRoute(): void
    {
        $registry = new RouteRegistry();
        $registry->registerRoutesForContentType(new ContentTypeDefinition(
            'season',
            'Seasons',
            'Season',
            'seasons',
            controller: SeasonController::class
        ));

        $paths = array_column($registry->all()['season']['public']['routes'], 1);

        $this->assertSame(['/{slug}/{productionSlug}', '/{slug}', ''], $paths);
    }

    /**
     * @return array<string, array{list<string>, list<string>}>
     */
    public static function imageRemovalRoutes(): array
    {
        return [
            'neither image' => [[], []],
            'featured image' => [['featured_image'], ['removeFeaturedImage']],
            'hero image' => [['hero_image'], ['removeHeroImage']],
            'both' => [['featured_image', 'hero_image'], ['removeFeaturedImage', 'removeHeroImage']],
        ];
    }

    /**
     * @param list<string> $supports
     * @param list<string> $expected
     */
    #[DataProvider('imageRemovalRoutes')]
    public function testImageRemovalRoutesFollowSupports(array $supports, array $expected): void
    {
        $registry = new RouteRegistry();
        $registry->registerRoutesForContentType(
            new ContentTypeDefinition('notice', 'Notices', 'Notice', 'notices', supports: $supports)
        );

        $actions = array_column($registry->all()['notice']['admin']['routes'], 2);

        $this->assertSame($expected, array_values(array_intersect($actions, ['removeFeaturedImage', 'removeHeroImage'])));
    }

    public function testAHierarchicalTypesSingleRouteMatchesAPath(): void
    {
        $registry = new RouteRegistry();
        $registry->registerRoutesForContentType(new ContentTypeDefinition(
            'page',
            'Pages',
            'Page',
            '',
            controller: PageController::class,
            hasArchive: false,
            hierarchical: true
        ));

        $this->assertSame(
            ['prefix' => '', 'routes' => [['get', '/{path:.+}', 'single']]],
            $registry->all()['page']['public']
        );
    }

    public function testATypeWhoseControllerHasNoSingleRouteKeepsOnlyItsArchive(): void
    {
        $registry = new RouteRegistry();
        $registry->registerRoutesForContentType(new ContentTypeDefinition(
            'production',
            'Productions',
            'Production',
            'productions',
            controller: ProductionController::class
        ));

        $this->assertSame([['get', '', 'archive']], $registry->all()['production']['public']['routes']);
    }

    public function testTheRootTypeClaimsNoPrefix(): void
    {
        $registry = $this->registryWithSeasons();
        $registry->registerRoutesForContentType(
            new ContentTypeDefinition('page', 'Pages', 'Page', '', hasArchive: false, hierarchical: true)
        );

        $registry->assertNoCollisions($this->routes(['/events/{slug}', null], ['/admin/settings', null]));

        $this->addToAssertionCount(1);
    }

    public function testATaxonomysOwnRoutesDoNotCollide(): void
    {
        $registry = $this->registryWithGenres();

        $registry->assertNoCollisions($this->routes(
            ['/admin/taxonomies/genre', RouteRegistry::taxonomyRouteName('genre', 'admin', 'index')],
            ['/genre/{slug}', RouteRegistry::taxonomyRouteName('genre', 'public', 'archive')],
        ));

        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function routesCollidingWithATaxonomy(): array
    {
        return [
            'under admin prefix' => ['/admin/taxonomies/genre/export', null],
            'under public prefix' => ['/genre/{slug}', null],
            'a content type\'s route name' => ['/genre/{slug}', RouteRegistry::routeName('genre', 'public', 'single')],
        ];
    }

    #[DataProvider('routesCollidingWithATaxonomy')]
    public function testRoutesTheTaxonomyDoesNotOwnCollide(string $pattern, ?string $name): void
    {
        $registry = $this->registryWithGenres();

        $this->expectException(RuntimeException::class);

        $registry->assertNoCollisions($this->routes([$pattern, $name]));
    }

    public function testATaxonomyWithoutAnArchiveHasNoPublicRoutes(): void
    {
        $registry = new RouteRegistry();
        $registry->registerRoutesForTaxonomy(new TaxonomyDefinition('genre', ['work'], 'Genres', 'Genre', urlPrefix: 'genre', hasArchive: false));

        $this->assertNull($registry->allTaxonomies()['genre']['public']);
        $registry->assertNoCollisions($this->routes(['/genre/{slug}', null]));
    }

    private function registryWithGenres(): RouteRegistry
    {
        $registry = new RouteRegistry();
        $registry->registerRoutesForTaxonomy(new TaxonomyDefinition('genre', ['work'], 'Genres', 'Genre', urlPrefix: 'genre'));

        return $registry;
    }

    private function registryWithSeasons(): RouteRegistry
    {
        $registry = new RouteRegistry();
        $registry->registerRoutesForContentType(new ContentTypeDefinition('season', 'Seasons', 'Season', 'seasons'));

        return $registry;
    }

    /**
     * @param array{string, ?string} ...$routes pattern and route name
     * @return list<RouteInterface>
     */
    private function routes(array ...$routes): array
    {
        $app = AppFactory::create();

        foreach ($routes as [$pattern, $name]) {
            $route = $app->get($pattern, static fn() => null);

            if ($name !== null) {
                $route->setName($name);
            }
        }

        return $app->getRouteCollector()->getRoutes();
    }
}
