<?php

namespace TheatreCMS\Services;

use DOMDocument;
use DOMXPath;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;

/**
 * Fetches basic HTML/Open Graph metadata for a URL so it can be rendered as
 * a bookmark-style link card (the "linkTool" EditorJS block, matching
 * @editorjs/link's client-side tool).
 *
 * Guards against SSRF: only http(s) URLs that resolve to public, non-reserved
 * IP addresses are fetched, redirects are not followed (a redirect could
 * otherwise reach an address that failed the initial check), and the
 * response body is capped.
 */
class LinkPreviewService
{
    private const MAX_BODY_BYTES = 1_000_000;
    private const TIMEOUT_SECONDS = 6;
    private const CONNECT_TIMEOUT_SECONDS = 3;

    public function __construct(private readonly ClientInterface $client)
    {
    }

    /**
     * @return array{success:int, link?:string, meta?:array<string,mixed>, error?:array{message:string}}
     */
    public function fetch(string $url): array
    {
        $url = trim($url);

        if (!$this->isSafeUrl($url)) {
            return $this->error('That URL could not be reached.');
        }

        try {
            $response = $this->client->request('GET', $url, [
                RequestOptions::TIMEOUT => self::TIMEOUT_SECONDS,
                RequestOptions::CONNECT_TIMEOUT => self::CONNECT_TIMEOUT_SECONDS,
                RequestOptions::ALLOW_REDIRECTS => false,
                RequestOptions::STREAM => true,
                RequestOptions::HEADERS => [
                    'User-Agent' => 'TheatreCMS-LinkPreview/1.0',
                    'Accept' => 'text/html,application/xhtml+xml',
                ],
            ]);
        } catch (GuzzleException) {
            return $this->error("Couldn't fetch the link data.");
        }

        if ($response->getStatusCode() !== 200) {
            return $this->error("Couldn't fetch the link data.");
        }

        $contentType = $response->getHeaderLine('Content-Type');
        if ($contentType !== '' && !str_contains($contentType, 'html')) {
            return $this->error('That link does not point to a web page.');
        }

        $body = $response->getBody()->read(self::MAX_BODY_BYTES);

        return [
            'success' => 1,
            'link' => $url,
            'meta' => $this->extractMeta($body, $url),
        ];
    }

    /**
     * Validates that a URL is http(s) and resolves to a public, non-reserved
     * IP address (rejecting loopback/private/link-local targets).
     */
    private function isSafeUrl(string $url): bool
    {
        $normalized = filter_var($url, FILTER_VALIDATE_URL);
        if ($normalized === false) {
            return false;
        }

        $scheme = parse_url($normalized, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = parse_url($normalized, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return false;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if (empty($ips)) {
            return false;
        }

        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string,mixed>
     */
    private function extractMeta(string $html, string $url): array
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?>' . $html);
        libxml_clear_errors();

        $xpath = new DOMXPath($doc);

        $title = $this->metaProperty($xpath, 'og:title') ?: $this->titleTag($doc);
        $description = $this->metaProperty($xpath, 'og:description') ?: $this->metaName($xpath, 'description');
        $image = $this->metaProperty($xpath, 'og:image');
        $siteName = $this->metaProperty($xpath, 'og:site_name');
        $icon = $this->iconHref($xpath);

        $meta = [
            'title' => $title !== '' ? $title : (string) (parse_url($url, PHP_URL_HOST) ?: $url),
            'description' => $description,
        ];

        if ($image !== '') {
            $meta['image'] = ['url' => $this->resolveUrl($image, $url)];
        }

        if ($siteName !== '') {
            $meta['publisher'] = $siteName;
        }

        if ($icon !== '') {
            $meta['icon'] = $this->resolveUrl($icon, $url);
        }

        return $meta;
    }

    private function metaProperty(DOMXPath $xpath, string $property): string
    {
        $node = $xpath->query(sprintf('//meta[@property="%s"]/@content', $property))->item(0);

        return $node ? trim($node->nodeValue) : '';
    }

    private function metaName(DOMXPath $xpath, string $name): string
    {
        $node = $xpath->query(sprintf('//meta[@name="%s"]/@content', $name))->item(0);

        return $node ? trim($node->nodeValue) : '';
    }

    private function iconHref(DOMXPath $xpath): string
    {
        $node = $xpath->query('//link[@rel="icon" or @rel="shortcut icon"]/@href')->item(0);

        return $node ? trim($node->nodeValue) : '';
    }

    private function titleTag(DOMDocument $doc): string
    {
        $node = $doc->getElementsByTagName('title')->item(0);

        return $node ? trim($node->textContent) : '';
    }

    /**
     * Resolves a (possibly relative or protocol-relative) URL against a base URL.
     */
    private function resolveUrl(string $maybeRelative, string $baseUrl): string
    {
        if ($maybeRelative === '' || preg_match('#^https?://#i', $maybeRelative)) {
            return $maybeRelative;
        }

        $base = parse_url($baseUrl);
        if (!isset($base['scheme'], $base['host'])) {
            return '';
        }

        $origin = $base['scheme'] . '://' . $base['host'] . (isset($base['port']) ? ':' . $base['port'] : '');

        if (str_starts_with($maybeRelative, '//')) {
            return $base['scheme'] . ':' . $maybeRelative;
        }

        if (str_starts_with($maybeRelative, '/')) {
            return $origin . $maybeRelative;
        }

        $basePath = $base['path'] ?? '/';
        $dir = substr($basePath, 0, strrpos($basePath, '/') + 1) ?: '/';

        return $origin . $dir . $maybeRelative;
    }

    /**
     * @return array{success:int, error:array{message:string}}
     */
    private function error(string $message): array
    {
        return [
            'success' => 0,
            'error' => ['message' => $message],
        ];
    }
}
