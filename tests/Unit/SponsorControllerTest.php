<?php

namespace TheatreCMS\Tests\Unit;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Response;
use Slim\Psr7\UploadedFile;
use TheatreCMS\Controllers\SponsorController;
use TheatreCMS\Repositories\SponsorRepository;
use TheatreCMS\Services\MediaUploadService;
use TheatreCMS\Tests\Includes\RendersControllerViews;
use TheatreCMS\Tests\Includes\UsesSqliteEntityManager;

#[AllowMockObjectsWithoutExpectations]
class SponsorControllerTest extends TestCase
{
    use UsesSqliteEntityManager;
    use RendersControllerViews;

    private EntityManager $em;
    private SponsorRepository $sponsors;
    private MediaUploadService|MockObject $uploads;

    protected function setUp(): void
    {
        $this->em = $this->createSqliteEntityManager();
        $this->sponsors = new SponsorRepository($this->em);
        $this->uploads = $this->createMock(MediaUploadService::class);
    }

    public function testIndexCreateAndEditRender(): void
    {
        $sponsor = $this->sponsors->create(['name' => 'Acme']);

        $this->controller()->index($this->request('GET'), new Response());
        $this->assertSame('admin/sponsors/index.html.twig', $this->rendered['template']);
        $this->assertCount(1, $this->rendered['data']['sponsors']);

        $this->controller()->create($this->request('GET'), new Response());
        $this->assertSame('admin/sponsors/create.html.twig', $this->rendered['template']);

        $this->controller()->edit($this->request('GET'), new Response(), ['id' => $sponsor->getId()]);
        $this->assertSame($sponsor, $this->rendered['data']['sponsor']);

        $this->controller()->quickCreate($this->request('GET'), new Response());
        $this->assertSame('admin/sponsors/_quick_create_modal.html.twig', $this->rendered['template']);
    }

    public function testStoreValidatesAndCreates(): void
    {
        $this->assertSame(400, $this->controller()->store($this->request('POST'), new Response())->getStatusCode());
        $this->controller()->store($this->request('POST', htmx: true), new Response());
        $this->assertSame('error', $this->rendered['data']['type']);

        $result = $this->controller()->store($this->request('POST', ['name' => 'Acme'], htmx: true), new Response());
        $sponsor = $this->sponsors->fetchBySlug('acme');
        $this->assertSame('/admin/sponsors/edit/' . $sponsor->getId(), $result->getHeaderLine('HX-Redirect'));

        $result = $this->controller()->store($this->request('POST', ['name' => 'Beta']), new Response());
        $this->assertStringStartsWith('/admin/sponsors/edit/', $result->getHeaderLine('Location'));
    }

    public function testQuickStoreRequiresNameAndTriggersEvent(): void
    {
        $result = $this->controller()->quickStore($this->request('POST', ['name' => '  ']), new Response());
        $this->assertSame('A name is required to create a sponsor.', $this->rendered['data']['message']);
        $this->assertSame('', $result->getHeaderLine('HX-Trigger'));

        $result = $this->controller()->quickStore(
            $this->request('POST', ['name' => 'Acme', 'websiteUrl' => 'https://acme.test']),
            new Response()
        );
        $trigger = json_decode($result->getHeaderLine('HX-Trigger'), true);
        $this->assertSame(['id' => $trigger['entityCreated']['id'], 'name' => 'Acme', 'type' => 'sponsor'], $trigger['entityCreated']);
        $this->assertSame('https://acme.test', $this->sponsors->fetchBySlug('acme')->getWebsiteUrl());
    }

    public function testUpdateValidatesBodyAndSponsor(): void
    {
        $this->assertSame(400, $this->controller()->update($this->request('POST'), new Response())->getStatusCode());
        $this->controller()->update($this->request('POST', htmx: true), new Response());
        $this->assertSame('Unable to save sponsor. Please check your input.', $this->rendered['data']['message']);

        $body = ['sponsorId' => '999', 'name' => 'X'];
        $this->assertSame(404, $this->controller()->update($this->request('POST', $body), new Response())->getStatusCode());
        $this->controller()->update($this->request('POST', $body, htmx: true), new Response());
        $this->assertSame('Sponsor not found.', $this->rendered['data']['message']);
    }

    public function testUpdateSavesFieldsAndStoresUploadedLogo(): void
    {
        $sponsor = $this->sponsors->create(['name' => 'Acme']);
        $this->uploads->expects($this->once())->method('store')->willReturn('/uploads/logo.png');

        $request = $this->request('POST', [
            'sponsorId' => (string) $sponsor->getId(),
            'name' => 'Acme Corp',
            'websiteUrl' => 'https://acme.test',
        ], htmx: true)->withUploadedFiles([
            'logoImage' => $this->upload('logo.png', 'image/png', UPLOAD_ERR_OK),
        ]);

        $this->controller()->update($request, new Response());

        $this->assertSame('admin/sponsors/_saved.html.twig', $this->rendered['template']);
        $this->assertSame('Acme Corp', $sponsor->getName());
        $this->assertSame('/uploads/logo.png', $sponsor->getLogoUrl());
    }

    public function testUpdateIgnoresNonImageAndFailedUploads(): void
    {
        $sponsor = $this->sponsors->create(['name' => 'Acme', 'logoUrl' => '/uploads/old.png']);
        $this->uploads->expects($this->never())->method('store');
        $body = ['sponsorId' => (string) $sponsor->getId(), 'name' => 'Acme', 'logoUrl' => '/uploads/old.png'];

        foreach (
            [
            $this->upload('doc.pdf', 'application/pdf', UPLOAD_ERR_OK),
            $this->upload('logo.png', 'image/png', UPLOAD_ERR_PARTIAL),
            $this->upload('', '', UPLOAD_ERR_NO_FILE),
            ] as $file
        ) {
            $result = $this->controller()->update(
                $this->request('POST', $body)->withUploadedFiles(['logoImage' => $file]),
                new Response()
            );
            $this->assertSame('/admin/sponsors', $result->getHeaderLine('Location'));
        }

        $this->assertSame('/uploads/old.png', $sponsor->getLogoUrl());
    }

    public function testRemoveLogoDeletesFileAndClearsUrl(): void
    {
        $sponsor = $this->sponsors->create(['name' => 'Acme', 'logoUrl' => '/uploads/logo.png']);
        $this->uploads->expects($this->exactly(2))->method('delete')->with('/uploads/logo.png');

        $this->controller()->removeLogo($this->request('DELETE', htmx: true), new Response(), ['id' => $sponsor->getId()]);
        $this->assertSame('admin/sponsors/_logo_removed.html.twig', $this->rendered['template']);
        $this->assertSame('', $sponsor->getLogoUrl());

        $sponsor->setLogoUrl('/uploads/logo.png');
        $result = $this->controller()->removeLogo($this->request('DELETE'), new Response(), ['id' => $sponsor->getId()]);
        $this->assertSame('/admin/sponsors/edit/' . $sponsor->getId(), $result->getHeaderLine('Location'));
    }

    public function testRemoveLogoReturnsNotFound(): void
    {
        $this->assertSame(404, $this->controller()->removeLogo($this->request('DELETE'), new Response(), ['id' => 9])->getStatusCode());

        $this->controller()->removeLogo($this->request('DELETE', htmx: true), new Response(), ['id' => 9]);
        $this->assertSame('Sponsor not found.', $this->rendered['data']['message']);
    }

    public function testDestroyDeletesAndRendersListOrRedirects(): void
    {
        $a = $this->sponsors->create(['name' => 'A']);
        $b = $this->sponsors->create(['name' => 'B']);

        $this->controller()->destroy($this->request('DELETE', htmx: true), new Response(), ['id' => $a->getId()]);
        $this->assertSame('admin/sponsors/_list.html.twig', $this->rendered['template']);
        $this->assertCount(1, $this->rendered['data']['sponsors']);

        $result = $this->controller()->destroy($this->request('DELETE'), new Response(), ['id' => $b->getId()]);
        $this->assertSame('/admin/sponsors', $result->getHeaderLine('Location'));
    }

    private function upload(string $name, string $type, int $error): UploadedFile
    {
        return new UploadedFile((new StreamFactory())->createStream('x'), $name, $type, 1, $error);
    }

    private function controller(): SponsorController
    {
        return new SponsorController($this->sponsors, $this->recordingTwig(), $this->uploads);
    }
}
