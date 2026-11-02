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
 * `theatrecms/{urlPrefix}`. Templates are looked up under the type's template directory
 * (`ContentTypeDefinition::templateDir()`). A hierarchical type's single page is found by its
 * path: its controller overrides fetchPublicItem() (see PageController).
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

    public static function hasSingleRoute(): bool
    {
        return true;
    }

    public function archive(Request $request, Response $response, array $args = []): Response
    {
        $definition = $this->publicContentType($request);
        $key = str_replace('-', '_', $definition->urlPrefix);
        $items = apply_filters("theatrecms/{$key}", $this->repository->fetchPublic(), $request);

        return $this->templateResolver()->renderArchive(
            $this->twig,
            $response,
            $definition->name,
            $definition->label,
            $items,
            [$key => $items],
            $definition->templateDir()
        );
    }

    public function single(Request $request, Response $response, array $args = []): Response
    {
        $definition = $this->publicContentType($request);
        $item = $this->fetchPublicItem($definition, $args);

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
            $definition->templateDir()
        );
    }

    /**
     * The publicly visible item a single route points at, or null. Looks it up by slug; a
     * hierarchical type's controller overrides this to look it up by path (`ManagesHierarchy`).
     *
     * @param array<string, mixed> $args the route arguments
     * @throws LogicException for a hierarchical type whose controller doesn't override this
     */
    protected function fetchPublicItem(ContentTypeDefinition $definition, array $args): ?object
    {
        if ($definition->hierarchical) {
            throw new LogicException(sprintf(
                'Content type "%s" is hierarchical, so %s must override fetchPublicItem() to find items by path.',
                $definition->name,
                static::class
            ));
        }

        return $this->repository->fetchPublicBySlug((string) ($args['slug'] ?? ''));
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
