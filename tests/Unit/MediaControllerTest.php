<?php

namespace TheatreCMS\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use TheatreCMS\Controllers\MediaController;
use TheatreCMS\Models\Media;
use TheatreCMS\Repositories\MediaRepository;
use TheatreCMS\Services\ImageVariantGenerator;
use TheatreCMS\Services\MediaUploadService;

#[AllowMockObjectsWithoutExpectations]
class MediaControllerTest extends TestCase
{
    public function testPickerForcesImageFilterWhenAnotherSupportedTypeIsRequested(): void
    {
        $repository = $this->createMock(MediaRepository::class);
        $repository->expects($this->once())
            ->method('fetchPage')
            ->with(1, 25, '', '', 'asc', ['type' => Media::TYPE_IMAGE])
            ->willReturn(['items' => [], 'total' => 0, 'page' => 1, 'perPage' => 25]);

        $request = $this->createMock(Request::class);
        $request->method('getQueryParams')->willReturn(['type' => Media::TYPE_PDF, 'target' => 'featured-image']);
        $response = $this->createMock(Response::class);

        $twig = $this->createMock(Twig::class);
        $twig->expects($this->once())
            ->method('render')
            ->with(
                $response,
                'admin/media/_picker.html.twig',
                $this->callback(static fn(array $data): bool => $data['type'] === Media::TYPE_IMAGE)
            )
            ->willReturn($response);

        $this->controller($repository, $twig)->picker($request, $response);
    }

    public function testLibraryRetainsArbitrarySupportedTypeFilter(): void
    {
        $repository = $this->createMock(MediaRepository::class);
        $repository->expects($this->once())
            ->method('fetchPage')
            ->with(1, 25, '', '', 'asc', ['type' => Media::TYPE_AUDIO])
            ->willReturn(['items' => [], 'total' => 0, 'page' => 1, 'perPage' => 25]);

        $request = $this->createMock(Request::class);
        $request->method('getQueryParams')->willReturn(['type' => Media::TYPE_AUDIO]);
        $request->method('getHeaderLine')->with('HX-Request')->willReturn('');
        $response = $this->createMock(Response::class);

        $twig = $this->createMock(Twig::class);
        $twig->expects($this->once())
            ->method('render')
            ->with(
                $response,
                'admin/media/index.html.twig',
                $this->callback(static fn(array $data): bool => $data['type'] === Media::TYPE_AUDIO)
            )
            ->willReturn($response);

        $this->controller($repository, $twig)->index($request, $response);
    }

    public function testSelectRejectsNonImageMedia(): void
    {
        $repository = $this->createMock(MediaRepository::class);
        $repository->method('fetch')->with(7)->willReturn(
            new Media('/uploads/program.pdf', 'program.pdf', Media::TYPE_PDF)
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Featured media must be an image.');

        $this->controller($repository, $this->createMock(Twig::class))->select(
            $this->createMock(Request::class),
            $this->createMock(Response::class),
            ['id' => '7']
        );
    }

    public function testSelectRendersValidImageMedia(): void
    {
        $image = new Media('/uploads/photo.jpg', 'photo.jpg', Media::TYPE_IMAGE);
        $repository = $this->createMock(MediaRepository::class);
        $repository->method('fetch')->with(8)->willReturn($image);
        $response = $this->createMock(Response::class);

        $twig = $this->createMock(Twig::class);
        $twig->expects($this->once())
            ->method('render')
            ->with(
                $response,
                'admin/partials/_featured_media_selection.html.twig',
                ['media' => $image, 'field' => 'featured']
            )
            ->willReturn($response);

        $actual = $this->controller($repository, $twig)->select(
            $this->createMock(Request::class),
            $response,
            ['id' => '8']
        );

        $this->assertSame($response, $actual);
    }

    private function controller(MediaRepository $repository, Twig $twig): MediaController
    {
        return new MediaController(
            $repository,
            $twig,
            $this->createMock(MediaUploadService::class),
            $this->createMock(ImageVariantGenerator::class)
        );
    }
}
