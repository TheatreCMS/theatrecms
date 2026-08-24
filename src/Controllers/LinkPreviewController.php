<?php

namespace TheatreCMS\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use TheatreCMS\Services\LinkPreviewService;

/**
 * Fetches title/description/image metadata for a URL so the "linkTool"
 * EditorJS block (@editorjs/link) can render it as a bookmark-style link card.
 *
 * GET /admin/link-preview/fetch?url=<url>
 *   Success: { "success": 1, "link": "<url>", "meta": { "title": ..., "description": ..., "image": { "url": ... } } }
 *   Error:   { "success": 0, "error": { "message": "<reason>" } }
 */
class LinkPreviewController
{
    public function __construct(private readonly LinkPreviewService $linkPreviewService)
    {
    }

    public function fetch(Request $request, Response $response): Response
    {
        $url = trim((string) ($request->getQueryParams()['url'] ?? ''));

        if ($url === '') {
            return $this->json($response, [
                'success' => 0,
                'error' => ['message' => 'A URL is required.'],
            ], 400);
        }

        $result = $this->linkPreviewService->fetch($url);

        return $this->json($response, $result, $result['success'] === 1 ? 200 : 422);
    }

    private function json(Response $response, array $payload, int $status): Response
    {
        $response->getBody()->write(json_encode($payload));

        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
