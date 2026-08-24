<?php

declare(strict_types=1);

namespace TheatreCMS\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Services\LinkPreviewService;

class LinkPreviewServiceTest extends TestCase
{
    /**
     * @param Response[] $responses
     */
    private function serviceWithMockResponses(array $responses): LinkPreviewService
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        return new LinkPreviewService($client);
    }

    public function testFetchRejectsUnsafeScheme(): void
    {
        $service = $this->serviceWithMockResponses([]);

        $result = $service->fetch('javascript:alert(1)');

        $this->assertSame(0, $result['success']);
    }

    public function testFetchRejectsFtpScheme(): void
    {
        $service = $this->serviceWithMockResponses([]);

        $result = $service->fetch('ftp://93.184.216.34/file');

        $this->assertSame(0, $result['success']);
    }

    public function testFetchRejectsEmptyUrl(): void
    {
        $service = $this->serviceWithMockResponses([]);

        $result = $service->fetch('');

        $this->assertSame(0, $result['success']);
    }

    #[DataProvider('privateAndReservedHostProvider')]
    public function testFetchRejectsPrivateAndReservedHosts(string $url): void
    {
        $service = $this->serviceWithMockResponses([]);

        $result = $service->fetch($url);

        $this->assertSame(0, $result['success'], "Expected {$url} to be rejected as unsafe");
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function privateAndReservedHostProvider(): array
    {
        return [
            'loopback' => ['http://127.0.0.1/'],
            'private class A' => ['http://10.0.0.5/'],
            'private class C' => ['http://192.168.1.1/'],
            'link-local / cloud metadata endpoint' => ['http://169.254.169.254/'],
            'ipv6 loopback' => ['http://[::1]/'],
        ];
    }

    public function testFetchExtractsOpenGraphMetadata(): void
    {
        $html = <<<HTML
            <html><head>
            <title>Fallback Title</title>
            <meta property="og:title" content="Dirty Laundry">
            <meta property="og:description" content="A raw, edge-of-your-seat family drama.">
            <meta property="og:image" content="/content/images/dirty-laundry.jpg">
            <meta property="og:site_name" content="Available Light Theatre">
            <link rel="icon" href="/favicon.ico">
            </head><body></body></html>
            HTML;

        $service = $this->serviceWithMockResponses([
            new Response(200, ['Content-Type' => 'text/html'], $html),
        ]);

        $result = $service->fetch('http://93.184.216.34/dirty-laundry/');

        $this->assertSame(1, $result['success']);
        $this->assertSame('Dirty Laundry', $result['meta']['title']);
        $this->assertSame('A raw, edge-of-your-seat family drama.', $result['meta']['description']);
        $this->assertSame('http://93.184.216.34/content/images/dirty-laundry.jpg', $result['meta']['image']['url']);
        $this->assertSame('Available Light Theatre', $result['meta']['publisher']);
        $this->assertSame('http://93.184.216.34/favicon.ico', $result['meta']['icon']);
    }

    public function testFetchFallsBackToTitleTagWhenNoOpenGraph(): void
    {
        $html = '<html><head><title>Plain Title</title></head><body></body></html>';

        $service = $this->serviceWithMockResponses([
            new Response(200, ['Content-Type' => 'text/html'], $html),
        ]);

        $result = $service->fetch('http://93.184.216.34/page');

        $this->assertSame(1, $result['success']);
        $this->assertSame('Plain Title', $result['meta']['title']);
    }

    public function testFetchReturnsErrorOnNonHtmlContentType(): void
    {
        $service = $this->serviceWithMockResponses([
            new Response(200, ['Content-Type' => 'application/pdf'], '%PDF-1.4'),
        ]);

        $result = $service->fetch('http://93.184.216.34/file.pdf');

        $this->assertSame(0, $result['success']);
    }

    public function testFetchReturnsErrorOnNon200Status(): void
    {
        $service = $this->serviceWithMockResponses([
            new Response(404, ['Content-Type' => 'text/html'], 'Not found'),
        ]);

        $result = $service->fetch('http://93.184.216.34/missing');

        $this->assertSame(0, $result['success']);
    }
}
