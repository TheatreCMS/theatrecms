<?php

declare(strict_types=1);

namespace TheatreCMS\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Services\RemoteImageFetcher;

class RemoteImageFetcherTest extends TestCase
{
    // A public IP literal, so the SSRF guard passes without a DNS lookup.
    private const PUBLIC_URL = 'https://93.184.216.34/images/Opening%20Night.png?w=800';

    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5E'
        . 'rkJggg==';

    /** @var list<array<string, mixed>> */
    private array $history = [];

    /**
     * @param list<Response|\Throwable> $responses
     */
    private function fetcher(array $responses): RemoteImageFetcher
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(\GuzzleHttp\Middleware::history($this->history));

        return new RemoteImageFetcher(new Client(['handler' => $stack]));
    }

    public function testFetchReturnsImageNamedAfterUrlWithDetectedExtension(): void
    {
        $png = base64_decode(self::PNG_1X1);
        // The remote server mislabels the type; the bytes decide.
        $fetcher = $this->fetcher([new Response(200, ['Content-Type' => 'application/octet-stream'], $png)]);

        $file = $fetcher->fetch(self::PUBLIC_URL);

        $this->assertSame('Opening Night.png', $file->getClientFilename());
        $this->assertSame('image/png', $file->getClientMediaType());
        $this->assertSame(strlen($png), $file->getSize());
        $this->assertSame($png, (string) $file->getStream());
    }

    public function testFetchDoesNotFollowRedirects(): void
    {
        $fetcher = $this->fetcher([new Response(302, ['Location' => 'http://127.0.0.1/secret.png'])]);

        $this->expectException(\InvalidArgumentException::class);

        try {
            $fetcher->fetch(self::PUBLIC_URL);
        } finally {
            $this->assertCount(1, $this->history);
        }
    }

    #[DataProvider('unsafeUrlProvider')]
    public function testFetchRejectsUnsafeUrlsWithoutRequestingThem(string $url): void
    {
        $fetcher = $this->fetcher([]);

        try {
            $fetcher->fetch($url);
            $this->fail("Expected {$url} to be rejected.");
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('That URL could not be reached.', $e->getMessage());
        }

        $this->assertCount(0, $this->history);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function unsafeUrlProvider(): array
    {
        return [
            'empty' => [''],
            'data uri' => ['data:image/png;base64,' . self::PNG_1X1],
            'file scheme' => ['file:///etc/passwd'],
            'loopback' => ['http://127.0.0.1/a.png'],
            'private' => ['http://10.0.0.5/a.png'],
            'cloud metadata' => ['http://169.254.169.254/latest/meta-data'],
            'ipv6 loopback' => ['http://[::1]/a.png'],
        ];
    }

    public function testFetchRejectsNonImageContent(): void
    {
        $fetcher = $this->fetcher([new Response(200, ['Content-Type' => 'image/png'], '<html>not an image</html>')]);

        $this->expectExceptionMessage('Only JPEG, PNG, GIF, and WebP images are allowed.');

        $fetcher->fetch(self::PUBLIC_URL);
    }

    public function testFetchRejectsDeclaredOversizedBody(): void
    {
        $fetcher = $this->fetcher([
            new Response(200, ['Content-Length' => (string) (RemoteImageFetcher::MAX_BYTES + 1)], 'x'),
        ]);

        $this->expectExceptionMessage('That image is larger than 10 MB.');

        $fetcher->fetch(self::PUBLIC_URL);
    }

    public function testFetchRejectsOversizedBodyWithoutContentLength(): void
    {
        $body = str_repeat('x', RemoteImageFetcher::MAX_BYTES + 1);
        $fetcher = $this->fetcher([new Response(200, [], $body)]);

        $this->expectExceptionMessage('That image is larger than 10 MB.');

        $fetcher->fetch(self::PUBLIC_URL);
    }

    public function testFetchReportsErrorStatusesAndConnectionFailures(): void
    {
        $fetcher = $this->fetcher([
            new Response(404),
            new ConnectException('timed out', new Request('GET', self::PUBLIC_URL)),
        ]);

        foreach ([1, 2] as $attempt) {
            try {
                $fetcher->fetch(self::PUBLIC_URL);
                $this->fail("Attempt {$attempt} should have failed.");
            } catch (\InvalidArgumentException $e) {
                $this->assertSame("Couldn't download that image.", $e->getMessage());
            }
        }
    }

    public function testFetchFallsBackToGenericNameWhenUrlHasNoFilename(): void
    {
        $fetcher = $this->fetcher([new Response(200, [], base64_decode(self::PNG_1X1))]);

        $file = $fetcher->fetch('https://93.184.216.34/');

        $this->assertSame('image.png', $file->getClientFilename());
    }
}
