<?php

namespace TheatreCMS\Tests\Unit;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Response;
use TheatreCMS\Controllers\SeasonController;
use TheatreCMS\Models\Media;
use TheatreCMS\Models\Season;
use TheatreCMS\Repositories\ContentMetaRepository;
use TheatreCMS\Repositories\SeasonRepository;
use TheatreCMS\Repositories\SponsorRepository;
use TheatreCMS\Tests\Includes\RendersControllerViews;
use TheatreCMS\Tests\Includes\UsesSqliteEntityManager;

#[AllowMockObjectsWithoutExpectations]
class SeasonControllerTest extends TestCase
{
    use UsesSqliteEntityManager;
    use RendersControllerViews;

    private EntityManager $em;
    private SeasonRepository $seasons;
    private SponsorRepository $sponsors;
    private ContentMetaRepository $meta;

    protected function setUp(): void
    {
        $this->em = $this->createSqliteEntityManager();
        $this->meta = new ContentMetaRepository($this->em);
        $this->seasons = new SeasonRepository($this->em, $this->meta);
        $this->sponsors = new SponsorRepository($this->em);
    }

    public function testIndexRendersPaginatedSeasons(): void
    {
        $this->seasons->create(['label' => '2025-26', 'startDate' => '2025-09-01', 'endDate' => '2026-06-01']);

        $this->controller()->index($this->request('GET'), new Response());

        $this->assertSame('admin/seasons/index.html.twig', $this->rendered['template']);
        $this->assertCount(1, $this->rendered['data']['seasons']);
        $this->assertSame(1, $this->rendered['data']['pagination']['total']);
    }

    public function testCreateAndEditExposeSponsorsAndHeroImage(): void
    {
        $this->sponsors->create(['name' => 'Acme']);
        $season = $this->season('2025-26');
        $this->meta->set('season', $season->getId(), 'hero_image_id', '42');

        $this->controller()->create($this->request('GET'), new Response());
        $this->assertSame('admin/seasons/create.html.twig', $this->rendered['template']);
        $this->assertCount(1, $this->rendered['data']['sponsors']);

        $this->controller()->edit($this->request('GET'), new Response(), ['id' => $season->getId()]);
        $this->assertSame('admin/seasons/edit.html.twig', $this->rendered['template']);
        $this->assertSame($season, $this->rendered['data']['season']);
        $this->assertSame('42', $this->rendered['data']['heroImageId']);
    }

    public function testShowRendersSeason(): void
    {
        $season = $this->season('2025-26');

        $this->controller()->show($this->request('GET'), new Response(), ['id' => $season->getId()]);

        $this->assertSame('seasons/show.html.twig', $this->rendered['template']);
        $this->assertSame($season, $this->rendered['data']['season']);
    }

    public function testStoreRejectsEmptyBody(): void
    {
        $this->assertSame(400, $this->controller()->store($this->request('POST'), new Response())->getStatusCode());

        $this->controller()->store($this->request('POST', htmx: true), new Response());
        $this->assertSame('admin/partials/_alert.html.twig', $this->rendered['template']);
        $this->assertSame('error', $this->rendered['data']['type']);
    }

    public function testStoreCreatesSeasonWithImagesAndSponsors(): void
    {
        $sponsor = $this->sponsors->create(['name' => 'Acme']);
        $image = $this->createTestMedia($this->em);

        $result = $this->controller()->store($this->request('POST', [
            'label' => '2025-26',
            'startDate' => '2025-09-01',
            'endDate' => '2026-06-01',
            'featuredImageId' => (string) $image->getId(),
            'heroImageId' => (string) $image->getId(),
            'sponsorshipSponsorIds' => $sponsor->getId() . ', 999',
        ], htmx: true), new Response());

        $season = $this->seasons->fetchBySlug('2025-26');
        $this->assertNotNull($season);
        $this->assertSame('/admin/seasons/edit/' . $season->getId(), $result->getHeaderLine('HX-Redirect'));
        $this->assertSame($image, $season->getFeaturedImage());
        $this->assertSame((string) $image->getId(), $this->meta->get('season', $season->getId(), 'hero_image_id'));
        $this->assertCount(1, $season->getSponsorships());
    }

    public function testStoreWithoutHtmxRedirectsToEdit(): void
    {
        $result = $this->controller()->store($this->request('POST', ['label' => 'Summer', 'startDate' => '2026-06-01', 'endDate' => '2026-08-31']), new Response());

        $this->assertStringStartsWith('/admin/seasons/edit/', $result->getHeaderLine('Location'));
    }

    public function testUpdateRequiresSeasonId(): void
    {
        $this->assertSame(400, $this->controller()->update($this->request('POST', ['label' => 'x']), new Response())->getStatusCode());

        $this->controller()->update($this->request('POST', ['label' => 'x'], htmx: true), new Response());
        $this->assertSame('admin/partials/_alert.html.twig', $this->rendered['template']);
    }

    public function testUpdateSavesFieldsAndReplacesSponsorships(): void
    {
        $first = $this->sponsors->create(['name' => 'First']);
        $second = $this->sponsors->create(['name' => 'Second']);
        $season = $this->season('Old');
        $this->controller()->update($this->request('POST', [
            'seasonId' => $season->getId(),
            'label' => 'Old',
            'startDate' => '2025-01-01',
            'endDate' => '2025-12-31',
            'sponsorshipSponsorIds' => [(string) $first->getId()],
        ]), new Response());

        $this->controller()->update($this->request('POST', [
            'seasonId' => $season->getId(),
            'label' => 'New',
            'startDate' => '2025-02-01',
            'endDate' => '2025-11-30',
            'overview' => 'An overview',
            'sponsorshipSponsorIds' => [(string) $second->getId(), ''],
        ], htmx: true), new Response());

        $this->assertSame('admin/seasons/_saved.html.twig', $this->rendered['template']);
        $this->assertSame('New', $season->getLabel());
        $this->assertSame('2025-02-01', $season->getStartDate()->format('Y-m-d'));
        $this->assertSame('An overview', $season->getOverview());
        $this->assertSame(
            [$second->getId()],
            array_map(fn($s) => $s->getSponsor()->getId(), $season->getSponsorships()->getValues())
        );
    }

    public function testUpdateWithoutHtmxRedirectsToList(): void
    {
        $season = $this->season('Old');

        $result = $this->controller()->update($this->request('POST', [
            'seasonId' => $season->getId(),
            'label' => 'New',
            'startDate' => '2025-01-01',
            'endDate' => '2025-12-31',
        ]), new Response());

        $this->assertSame('/admin/seasons', $result->getHeaderLine('Location'));
    }

    public function testUpdateRejectsNonImageFeaturedMedia(): void
    {
        $season = $this->season('Old');
        $pdf = $this->createTestMedia($this->em, Media::TYPE_PDF);

        $result = $this->controller()->update($this->request('POST', [
            'seasonId' => $season->getId(),
            'label' => 'Old',
            'startDate' => '2025-01-01',
            'endDate' => '2025-12-31',
            'featuredImageId' => (string) $pdf->getId(),
        ]), new Response());

        $this->assertSame(403, $result->getStatusCode());
    }

    public function testDestroyDeletesAndRendersListOrRedirects(): void
    {
        $a = $this->season('A');
        $b = $this->season('B');

        $this->controller()->destroy($this->request('DELETE', htmx: true), new Response(), ['id' => $a->getId()]);
        $this->assertSame('admin/seasons/_list.html.twig', $this->rendered['template']);
        $this->assertCount(1, $this->rendered['data']['seasons']);

        $result = $this->controller()->destroy(
            $this->request('DELETE', query: ['page' => '2']),
            new Response(),
            ['id' => $b->getId()]
        );
        $this->assertSame('/admin/seasons?page=2', $result->getHeaderLine('Location'));
        $this->assertSame([], $this->seasons->fetchAll());
    }

    public function testRemoveFeaturedAndHeroImage(): void
    {
        $image = $this->createTestMedia($this->em);
        $season = $this->season('A');
        $season->setFeaturedImage($image);
        $this->meta->set('season', $season->getId(), 'hero_image_id', (string) $image->getId());
        $this->em->flush();

        $this->controller()->removeFeaturedImage($this->request('DELETE', htmx: true), new Response(), ['id' => $season->getId()]);
        $this->assertNull($season->getFeaturedImage());
        $this->assertSame('admin/partials/_featured_image_field.html.twig', $this->rendered['template']);

        $result = $this->controller()->removeHeroImage($this->request('DELETE'), new Response(), ['id' => $season->getId()]);
        $this->assertNull($this->meta->get('season', $season->getId(), 'hero_image_id'));
        $this->assertSame('/admin/seasons/edit/' . $season->getId(), $result->getHeaderLine('Location'));

        $this->assertSame(404, $this->controller()->removeHeroImage($this->request('DELETE'), new Response(), ['id' => 999])->getStatusCode());
        $this->controller()->removeFeaturedImage($this->request('DELETE', htmx: true), new Response(), ['id' => 999]);
        $this->assertSame('Season not found.', $this->rendered['data']['message']);
    }

    private function season(string $label): Season
    {
        return $this->seasons->create(['label' => $label, 'startDate' => '2025-09-01', 'endDate' => '2026-06-01']);
    }

    private function controller(): SeasonController
    {
        return new SeasonController($this->seasons, $this->em, $this->recordingTwig(), $this->sponsors, $this->meta);
    }
}
