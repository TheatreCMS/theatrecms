<?php

namespace TheatreCMS\Theme;

use Psr\Http\Message\ResponseInterface as Response;
use Slim\Views\Twig;

/**
 * Resolves a template from a list of candidates, returning the first one that exists. This is used
 * to implement the template hierarchy, where more specific templates are checked before more generic ones.
 *
 * For example, when rendering a single production, the candidates might be:
 * - productions/single-{production-slug}.html.twig
 * - productions/single.html.twig
 * - single.html.twig
 * - index.html.twig
 *
 * `resolveSingle()`/`resolveList()` (and their `render*()` counterparts) build exactly that
 * candidate list for the two shapes every frontend route needs, so routes don't each repeat it.
 */
class TemplateResolver
{
    public function __construct(
        private readonly TitleResolver $titleResolver,
        private readonly QueriedObject $queriedObject,
        private readonly SeoTagBuilder $seoTagBuilder
    ) {
    }

    public function resolve(Twig $twig, string ...$candidates): string
    {
        if (empty($candidates)) {
            throw new \InvalidArgumentException('At least one template candidate must be provided.');
        }

        $loader = $twig->getLoader();

        foreach ($candidates as $template) {
            if ($loader->exists($template)) {
                return $template;
            }
        }

        return end($candidates); // fallback to last (most generic)
    }

    /**
     * Template hierarchy for a single content-item view:
     * {type}/single-{slug}.html.twig -> {type}/single.html.twig -> single.html.twig -> index.html.twig
     */
    public function resolveSingle(Twig $twig, string $type, string $slug): string
    {
        return $this->resolve(
            $twig,
            "$type/single-$slug.html.twig",
            "$type/single.html.twig",
            'single.html.twig',
            'index.html.twig'
        );
    }

    /**
     * Template hierarchy for a content-type list/archive view:
     * {type}/list.html.twig -> list.html.twig -> index.html.twig
     */
    public function resolveList(Twig $twig, string $type): string
    {
        return $this->resolve($twig, "$type/list.html.twig", 'list.html.twig', 'index.html.twig');
    }

    /**
     * Resolve and render a single content-item view in one step.
     *
     * Also adds three generic context keys so every theme's fallback templates can rely
     * on them regardless of which content type is being rendered, without clobbering
     * routes (like `pages/{slug}`) that already pass their own richer keys in `$context`:
     * - `page` (currently just `title`, resolved via `TitleResolver`), used by
     *   `layouts/base.html.twig`.
     * - `posts` (the entity wrapped in a 1-element array), used by the generic
     *   `index.html.twig` fallback that both bundled themes rely on when no type-specific
     *   single template exists yet.
     * - `seo` (a `SeoMeta` value object built by `SeoTagBuilder`), consumed by
     *   `<title>` blocks and `theme_head()`/`ThemeHeadExtension`. It's a top-level key
     *   (not nested under `page`) specifically so it survives routes like `pages/{slug}`
     *   that override `page` with the raw entity itself.
     *
     * @param array<string, mixed> $context
     */
    public function renderSingle(
        Twig $twig,
        Response $response,
        string $type,
        object $entity,
        array $context = []
    ): Response {
        $slug = method_exists($entity, 'getSlug') ? (string) $entity->getSlug() : '';
        $context += [
            'posts' => [$entity],
            'page' => ['title' => $this->titleResolver->resolve($entity)],
            'seo' => $this->seoTagBuilder->forEntity($entity, $type),
        ];

        $this->queriedObject->setSingle($type, $entity);

        return $twig->render($response, $this->resolveSingle($twig, $type, $slug), $context);
    }

    /**
     * Resolve and render a content-type list/archive view in one step.
     *
     * Adds the same generic context keys `renderSingle()` adds:
     * - `page` (there being no single entity to derive a title from, the caller supplies
     *   the page title directly).
     * - `posts` (the raw `$items` collection), used by the generic `index.html.twig`
     *   fallback that both bundled themes rely on when no type-specific list template
     *   exists yet.
     * - `seo` (a `SeoMeta` value object built by `SeoTagBuilder`, from the archive's
     *   type/label rather than a single entity).
     *
     * @param array<int, object> $items
     * @param array<string, mixed> $context
     */
    public function renderList(
        Twig $twig,
        Response $response,
        string $type,
        string $pageTitle,
        array $items,
        array $context = []
    ): Response {
        $context += [
            'posts' => $items,
            'page' => ['title' => $pageTitle],
            'seo' => $this->seoTagBuilder->forArchive($type, $pageTitle),
        ];

        $this->queriedObject->setArchive($type);

        return $twig->render($response, $this->resolveList($twig, $type), $context);
    }
}
