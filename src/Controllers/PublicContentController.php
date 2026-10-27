<?php

namespace TheatreCMS\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * A controller that serves a public content type's frontend pages. `app/routes.php` refuses
 * to boot when a public type's controller doesn't implement it.
 *
 * Both actions receive the type's `ContentTypeDefinition` as the request attribute named
 * after its class (see `RendersPublicContent`, which implements them).
 */
interface PublicContentController
{
    /**
     * The archive page at `/{urlPrefix}`; only routed when the type has an archive.
     */
    public function archive(Request $request, Response $response, array $args = []): Response;

    /**
     * The single page at `/{urlPrefix}/{slug}`.
     */
    public function single(Request $request, Response $response, array $args = []): Response;

    /**
     * Extra public routes under `/{urlPrefix}`, as `[method, path, action]` (e.g. a season's
     * `/{slug}/{productionSlug}`). They're registered ahead of the archive and single routes.
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    public static function additionalPublicRoutes(): array;
}
