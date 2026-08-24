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
    public function __construct(private readonly TitleResolver $titleResolver)
    {
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
     * Also adds a generic `page` object (currently just `title`, resolved via
     * `TitleResolver`) to the context, so every theme's `layouts/base.html.twig` can rely
     * on `page.title` regardless of which content type is being rendered — without
     * clobbering routes (like `pages/{slug}`) that already pass their own richer `page`
     * entity in `$context`.
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
        $context += ['page' => ['title' => $this->titleResolver->resolve($entity)]];

        return $twig->render($response, $this->resolveSingle($twig, $type, $slug), $context);
    }

    /**
     * Resolve and render a content-type list/archive view in one step, with the same
     * `page` context object `renderSingle()` adds (there being no single entity to derive
     * a title from, the caller supplies the page title directly).
     *
     * @param array<string, mixed> $context
     */
    public function renderList(
        Twig $twig,
        Response $response,
        string $type,
        string $pageTitle,
        array $context = []
    ): Response {
        $context += ['page' => ['title' => $pageTitle]];

        return $twig->render($response, $this->resolveList($twig, $type), $context);
    }
}
