<?php

namespace TheatreCMS\Services;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\UploadedFile;
use GuzzleHttp\Psr7\Utils;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Downloads an image from a URL an editor pasted into EditorJS, so it can be stored like a
 * regular upload (MediaUploadService::store()) instead of hot-linking the remote copy.
 *
 * Same SSRF guarding as LinkPreviewService: only URLs passing PublicUrlGuard, no redirects,
 * and a capped body. The image type comes from the downloaded bytes, never from the remote
 * Content-Type header or the URL's extension.
 */
class RemoteImageFetcher
{
    public const MAX_BYTES = 10 * 1024 * 1024;
    private const TIMEOUT_SECONDS = 15;
    private const CONNECT_TIMEOUT_SECONDS = 3;
    private const READ_CHUNK_BYTES = 65536;

    /** Detected MIME type => stored file extension. */
    private const EXTENSIONS_BY_MIME = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    public function __construct(private readonly ClientInterface $client)
    {
    }

    /**
     * Fetches the image at $url as an in-memory uploaded file, named after the URL's last
     * path segment with an extension matching its real type.
     *
     * @throws \InvalidArgumentException with an editor-facing message when the URL is
     *         rejected, can't be fetched, is too large, or isn't a supported image.
     */
    public function fetch(string $url): UploadedFileInterface
    {
        $url = trim($url);

        if (!PublicUrlGuard::isPublicHttpUrl($url)) {
            throw new \InvalidArgumentException('That URL could not be reached.');
        }

        try {
            $response = $this->client->request('GET', $url, [
                RequestOptions::TIMEOUT => self::TIMEOUT_SECONDS,
                RequestOptions::CONNECT_TIMEOUT => self::CONNECT_TIMEOUT_SECONDS,
                RequestOptions::ALLOW_REDIRECTS => false,
                RequestOptions::HTTP_ERRORS => false,
                RequestOptions::STREAM => true,
                RequestOptions::HEADERS => [
                    'User-Agent' => 'TheatreCMS-ImageFetch/1.0',
                    'Accept' => 'image/*',
                ],
            ]);
        } catch (GuzzleException) {
            throw new \InvalidArgumentException("Couldn't download that image.");
        }

        if ($response->getStatusCode() !== 200) {
            throw new \InvalidArgumentException("Couldn't download that image.");
        }

        $declaredLength = $response->getHeaderLine('Content-Length');
        if ($declaredLength !== '' && (int) $declaredLength > self::MAX_BYTES) {
            throw new \InvalidArgumentException($this->tooLargeMessage());
        }

        $body = $response->getBody();
        $bytes = '';
        while (!$body->eof()) {
            $bytes .= $body->read(self::READ_CHUNK_BYTES);
            if (strlen($bytes) > self::MAX_BYTES) {
                throw new \InvalidArgumentException($this->tooLargeMessage());
            }
        }

        $mime = $bytes === '' ? false : (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $extension = is_string($mime) ? (self::EXTENSIONS_BY_MIME[$mime] ?? null) : null;
        if ($extension === null) {
            throw new \InvalidArgumentException('Only JPEG, PNG, GIF, and WebP images are allowed.');
        }

        return new UploadedFile(
            Utils::streamFor($bytes),
            strlen($bytes),
            UPLOAD_ERR_OK,
            $this->baseName($url) . '.' . $extension,
            $mime
        );
    }

    /**
     * The URL's last path segment without its extension, e.g. "poster" for
     * https://example.com/img/poster.jpg?w=800; "image" when there's nothing usable.
     */
    private function baseName(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $name = pathinfo(rawurldecode(basename($path)), PATHINFO_FILENAME);

        return $name !== '' ? $name : 'image';
    }

    private function tooLargeMessage(): string
    {
        return sprintf('That image is larger than %d MB.', self::MAX_BYTES / 1024 / 1024);
    }
}
