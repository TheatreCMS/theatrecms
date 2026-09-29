<?php

namespace TheatreCMS\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Admin\AdminMenuRegistry;
use TheatreCMS\Auth\AuthorizationService;
use TheatreCMS\Enums\ContentStatus;
use TheatreCMS\Models\Media;
use TheatreCMS\Models\Page;
use TheatreCMS\Models\Production;
use TheatreCMS\Models\Season;
use TheatreCMS\Models\Term;
use TheatreCMS\Models\Venue;
use TheatreCMS\Repositories\TermRelationshipRepository;
use TheatreCMS\Repositories\TermRepository;
use TheatreCMS\Taxonomy\TaxonomyRegistry;
use TheatreCMS\Theme\AddressResolver;
use TheatreCMS\Theme\EndDateResolver;
use TheatreCMS\Theme\ExcerptResolver;
use TheatreCMS\Theme\StartDateResolver;
use TheatreCMS\Theme\TitleResolver;
use TheatreCMS\Twig\AddressExtension;
use TheatreCMS\Twig\AdminMenuExtension;
use TheatreCMS\Twig\CapabilityExtension;
use TheatreCMS\Twig\ConditionalTagsExtension;
use TheatreCMS\Twig\EndDateExtension;
use TheatreCMS\Twig\ExcerptExtension;
use TheatreCMS\Twig\HooksExtension;
use TheatreCMS\Twig\MediaTypeIconExtension;
use TheatreCMS\Twig\StartDateExtension;
use TheatreCMS\Twig\TaxonomyExtension;
use TheatreCMS\Twig\TermsExtension;
use TheatreCMS\Twig\TitleExtension;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * The small Twig extensions that each expose a function or two over a resolver or service.
 */
#[AllowMockObjectsWithoutExpectations]
class TwigExtensionsTest extends TestCase
{
    public function testExtensionsRegisterTheirFunctions(): void
    {
        $authorization = $this->createMock(AuthorizationService::class);
        $expected = [
            'the_address' => new AddressExtension(new AddressResolver()),
            'admin_menu' => new AdminMenuExtension(new AdminMenuRegistry()),
            'can' => new CapabilityExtension($authorization),
            'is_single,is_item,is_item_id,is_archive,is_singular,is_tax' => new ConditionalTagsExtension(),
            'the_end_date' => new EndDateExtension(new EndDateResolver()),
            'the_excerpt' => new ExcerptExtension(new ExcerptResolver()),
            'apply_filters' => new HooksExtension(),
            'media_type_icon' => new MediaTypeIconExtension(),
            'the_start_date' => new StartDateExtension(new StartDateResolver()),
            'taxonomies_for,admin_taxonomies,taxonomy_terms' => new TaxonomyExtension(
                new TaxonomyRegistry(),
                $authorization,
                $this->createMock(TermRepository::class),
            ),
            'get_terms' => new TermsExtension($this->createMock(TermRelationshipRepository::class)),
            'the_title' => new TitleExtension(new TitleResolver()),
        ];

        foreach ($expected as $names => $extension) {
            $this->assertSame(explode(',', $names), $this->functionNames($extension), get_class($extension));
        }
    }

    public function testTheAddressEscapesAndBreaksLines(): void
    {
        $venue = new Venue('Main', '1 <Stage> Rd', 'Testville', 'TS', '00000');

        $html = (string) (new AddressExtension(new AddressResolver()))->theAddress($venue);

        $this->assertSame("1 &lt;Stage&gt; Rd<br>\nTestville, TS 00000", $html);
    }

    public function testTheExcerptEscapesAndBreaksLines(): void
    {
        $production = $this->production();
        $production->setExcerpt("One & two\nthree");

        $html = (string) (new ExcerptExtension(new ExcerptResolver()))->theExcerpt($production);

        $this->assertSame("One &amp; two<br>\nthree", $html);
    }

    public function testStartAndEndDatesFormatSeasonAndProductionDates(): void
    {
        $season = $this->season();
        $production = $this->production($season);
        $production->setOpening(new \DateTime('2025-10-01'));
        $production->setClosing(new \DateTime('2025-10-31'));

        $start = new StartDateExtension(new StartDateResolver());
        $end = new EndDateExtension(new EndDateResolver());

        $this->assertSame('September 1, 2025', $start->theStartDate($season));
        $this->assertSame('2025-10-01', $start->theStartDate($production, 'Y-m-d'));
        $this->assertSame('', $start->theStartDate(new \stdClass()));
        $this->assertSame('June 1, 2026', $end->theEndDate($season));
        $this->assertSame('2025-10-31', $end->theEndDate($production, 'Y-m-d'));
        $this->assertSame('', $end->theEndDate(new \stdClass()));
    }

    public function testStartDateIsEmptyForProductionWithoutOpeningOrPerformances(): void
    {
        $this->assertSame('', (new StartDateExtension(new StartDateResolver()))->theStartDate($this->production()));
    }

    public function testTitleCapabilityAndIconDelegate(): void
    {
        $authorization = $this->createMock(AuthorizationService::class);
        $authorization->method('can')->willReturnCallback(fn(string $cap) => $cap === 'edit_posts');
        $capability = new CapabilityExtension($authorization);
        $icons = new MediaTypeIconExtension();

        $this->assertSame('About', (new TitleExtension(new TitleResolver()))->theTitle(new Page('About', ContentStatus::DRAFT, 'x')));
        $this->assertTrue($capability->can('edit_posts'));
        $this->assertFalse($capability->can('manage_options'));
        $this->assertStringContainsString('polygon', $icons->icon(Media::TYPE_VIDEO));
        $this->assertNotSame($icons->icon(Media::TYPE_PDF), $icons->icon('unknown'));
    }

    public function testTaxonomyFunctionsFilterByCapabilityAndFetchTerms(): void
    {
        $registry = new TaxonomyRegistry();
        $registry->register('genre', ['work'], ['capability' => 'manage_people']);
        $registry->register('era', ['work'], ['capability' => 'manage_options']);

        $authorization = $this->createMock(AuthorizationService::class);
        $authorization->method('can')->willReturnCallback(fn(string $cap) => $cap === 'manage_people');

        $term = $this->createMock(Term::class);
        $terms = $this->createMock(TermRepository::class);
        $terms->method('fetchByTaxonomy')->with('genre')->willReturn([$term]);

        $relationships = $this->createMock(TermRelationshipRepository::class);
        $relationships->method('termsFor')->with('work', 3, 'genre')->willReturn([$term]);

        $extension = new TaxonomyExtension($registry, $authorization, $terms);

        $this->assertSame(['genre', 'era'], array_keys($extension->taxonomiesFor('work')));
        $this->assertSame(['genre'], array_keys($extension->adminTaxonomies('work')));
        $this->assertSame([$term], $extension->taxonomyTerms('genre'));
        $this->assertSame([$term], (new TermsExtension($relationships))->getTerms('work', 3, 'genre'));
    }

    /**
     * @return string[]
     */
    private function functionNames(AbstractExtension $extension): array
    {
        return array_map(fn(TwigFunction $f) => $f->getName(), $extension->getFunctions());
    }

    private function season(): Season
    {
        $season = new Season('2025-26', '2025-26');
        $season->setStartDate(new \DateTime('2025-09-01'));
        $season->setEndDate(new \DateTime('2026-06-01'));

        return $season;
    }

    private function production(?Season $season = null): Production
    {
        return new Production('Hamlet', $season ?? $this->season());
    }
}
