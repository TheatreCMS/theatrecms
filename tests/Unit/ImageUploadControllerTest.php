<?php

declare(strict_types=1);

namespace TheatreCMS\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use TheatreCMS\Controllers\ImageUploadController;
use TheatreCMS\Services\MediaUploadService;
use TheatreCMS\Services\RemoteImageFetcher;

class ImageUploadControllerTest extends TestCase
{
    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5E'
        . 'rkJggg==';

    private string $publicRoot;

    protected function setUp(): void
    {
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

        return new ImageUploadController(
            new MediaUploadService($this->publicRoot),
            new RemoteImageFetcher($client)
        );
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
    }
}
