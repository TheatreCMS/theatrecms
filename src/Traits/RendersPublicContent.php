<?php

namespace TheatreCMS\Traits;

use LogicException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use TheatreCMS\ContentType\ContentTypeDefinition;
use TheatreCMS\Theme\TemplateResolver;

/**
 * Implements `PublicContentController` for any BaseController subclass: publicly visible
 * items from its repository, rendered through the theme's template hierarchy.
 *
 * Filters and context keys match the frontend routes these replace, so themes keep working:
 * a single page gets `{type}` (e.g. `season`) through the `theatrecms/{type}` filter, and an
 * archive gets `{urlPrefix}` with hyphens as underscores (e.g. `seasons`) through
 * `theatrecms/{urlPrefix}`. Templates are looked up under the `{urlPrefix}/` directory.
 */
trait RendersPublicContent
{
    abstract protected function templateResolver(): TemplateResolver;

    /**
     * None by default; override to add routes such as a season's nested production page.
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    public static function additionalPublicRoutes(): array
    {
        return [];
    }

    public function archive(Request $request, Response $response, array $args = []): Response
    {
        $definition = $this->publicContentType($request);
        $key = str_replace('-', '_', $definition->urlPrefix);
        $items = apply_filters("theatrecms/{$key}", $this->repository->fetchPublic(), $request);

        return $this->templateResolver()->renderList(
            $this->twig,
            $response,
            $definition->name,
            $definition->label,
            $items,
            [$key => $items],
            $definition->urlPrefix
        );
    }

    public function single(Request $request, Response $response, array $args = []): Response
    {
        $definition = $this->publicContentType($request);
        $item = $this->repository->fetchPublicBySlug((string) ($args['slug'] ?? ''));

        if (!$item) {
            $response->getBody()->write("{$definition->singularLabel} not found");

            return $response->withStatus(404);
        }

        $item = apply_filters("theatrecms/{$definition->name}", $item, $request, $args);

        return $this->templateResolver()->renderSingle(
            $this->twig,
            $response,
            $definition->name,
            $item,
            [$definition->name => $item],
            $definition->urlPrefix
        );
    }

    /**
     * @throws LogicException when the route didn't attach the definition (see `app/routes.php`)
     */
    private function publicContentType(Request $request): ContentTypeDefinition
    {
        $definition = $request->getAttribute(ContentTypeDefinition::class);

        if (!$definition instanceof ContentTypeDefinition) {
            throw new LogicException('The request has no ContentTypeDefinition attribute.');
        }

        return $definition;
    }
}
