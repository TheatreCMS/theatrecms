<?php

declare(strict_types=1);

namespace TheatreCMS\Tests\Unit;

use Doctrine\ORM\EntityManager;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use GuzzleHttp\Psr7\UploadedFile;
use GuzzleHttp\Psr7\Utils;
use Intervention\Image\ImageManager;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use TheatreCMS\Controllers\ImageUploadController;
use TheatreCMS\Models\Media;
use TheatreCMS\Repositories\MediaRepository;
use TheatreCMS\Services\ImageVariantGenerator;
use TheatreCMS\Services\MediaImporter;
use TheatreCMS\Services\MediaUploadService;
use TheatreCMS\Services\RemoteImageFetcher;
use TheatreCMS\Tests\Includes\UsesSqliteEntityManager;
use TheatreCMS\Theme\ImageSizeRegistry;

class ImageUploadControllerTest extends TestCase
{
    use UsesSqliteEntityManager;

    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5E'
        . 'rkJggg==';

    private string $publicRoot;

    private EntityManager $em;

    protected function setUp(): void
    {
        $this->em = $this->createSqliteEntityManager();
        $this->publicRoot = __DIR__ . '/.theatrecms-image-upload-test-' . uniqid();
        mkdir($this->publicRoot . '/uploads', 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->publicRoot . '/uploads/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->publicRoot . '/uploads');
        rmdir($this->publicRoot);
    }

    /**
     * @param list<GuzzleResponse> $remoteResponses
     */
    private function controller(array $remoteResponses = []): ImageUploadController
    {
        $client = new Client(['handler' => HandlerStack::create(new MockHandler($remoteResponses))]);
        $uploads = new MediaUploadService($this->publicRoot);
        $sizes = new ImageSizeRegistry();
        $sizes->register('thumbnail', 150, 150, true);

        return new ImageUploadController(
            new MediaImporter(
                $uploads,
                new MediaRepository($this->em),
                new ImageVariantGenerator(ImageManager::gd(), $uploads, $this->em, $sizes)
            ),
            new RemoteImageFetcher($client)
        );
    }

    /**
     * @return list<Media>
     */
    private function library(): array
    {
        return $this->em->getRepository(Media::class)->findAll();
    }

    /**
     * @param array<string, mixed>|null $body
     */
    private function fetch(ImageUploadController $controller, ?array $body): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/admin/images/fetch')
            ->withParsedBody($body);

        return $controller->fetch($request, (new ResponseFactory())->createResponse());
    }

    /**
     * @return array<string, mixed>
     */
    private function json(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true);
    }

    public function testFetchStoresTheRemoteImageAndReturnsItsUrl(): void
    {
        $png = base64_decode(self::PNG_1X1);
        $controller = $this->controller([new GuzzleResponse(200, ['Content-Type' => 'image/png'], $png)]);

        $response = $this->fetch($controller, ['url' => 'https://93.184.216.34/media/Cast%20Photo.png']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['success' => 1, 'file' => ['url' => '/uploads/cast-photo.png']], $this->json($response));
        $this->assertSame($png, file_get_contents($this->publicRoot . '/uploads/cast-photo.png'));

        [$media] = $this->library();
        $this->assertSame('/uploads/cast-photo.png', $media->getUrl());
        $this->assertSame('Cast Photo.png', $media->getOriginalFilename());
        $this->assertSame('image/png', $media->getMimeType());
        $this->assertSame(Media::TYPE_IMAGE, $media->getMediaType());
    }

    public function testUploadAddsTheImageToTheMediaLibraryWithItsImageSizes(): void
    {
        $png = base64_decode(self::PNG_1X1);
        $file = new UploadedFile(Utils::streamFor($png), strlen($png), UPLOAD_ERR_OK, 'Poster.png', 'image/png');
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/admin/images/upload')
            ->withUploadedFiles(['image' => $file]);

        $response = $this->controller()->upload($request, (new ResponseFactory())->createResponse());

        $this->assertSame(['success' => 1, 'file' => ['url' => '/uploads/poster.png']], $this->json($response));

        $library = $this->library();
        $this->assertCount(1, $library);
        $this->assertSame('/uploads/poster.png', $library[0]->getUrl());
        $this->assertSame(strlen($png), $library[0]->getSizeBytes());

        $variants = $library[0]->getVariants();
        $this->assertCount(1, $variants);
        $this->assertSame('/uploads/poster-thumbnail.png', $variants->first()->getUrl());
        $this->assertFileExists($this->publicRoot . '/uploads/poster-thumbnail.png');
    }

    public function testFetchRequiresAUrl(): void
    {
        $response = $this->fetch($this->controller(), null);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(0, $this->json($response)['success']);
    }

    public function testFetchReportsRejectedUrlsInTheEditorJsErrorShape(): void
    {
        $response = $this->fetch($this->controller(), ['url' => 'http://127.0.0.1/a.png']);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(
            ['success' => 0, 'error' => ['message' => 'That URL could not be reached.']],
            $this->json($response)
        );
        $this->assertSame([], glob($this->publicRoot . '/uploads/*'));
        $this->assertSame([], $this->library());
    }
}
