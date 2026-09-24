<?php

use TheatreCMS\Middleware\RequireTwigMiddleware;
use TheatreCMS\Repositories\TermRepository;
use TheatreCMS\Taxonomy\TaxonomyDefinition;
use TheatreCMS\Taxonomy\TaxonomyRegistry;
use TheatreCMS\Taxonomy\TermArchiveQuery;
use TheatreCMS\Theme\ContentTypeRegistry;
use TheatreCMS\Theme\TemplateResolver;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Term archives: one `/{url_prefix}/{term-slug}` route per taxonomy with `has_archive` on.
 * Routes are registered per prefix rather than as a `/{taxonomy}/{slug}` wildcard, which
 * would collide with other two-segment routes like `/seasons/{slug}/{productionSlug}`.
 *
 * @var TemplateResolver $resolver
 * @var ContentTypeRegistry $contentTypes
 */

if (isset($app)) {
    $container = $app->getContainer();

    /** @var TaxonomyRegistry $taxonomyRegistry */
    $taxonomyRegistry = $container->get(TaxonomyRegistry::class);
    $reservedPrefixes = array_map(
        static fn(string $type): string => $contentTypes->prefix($type),
        ['seasons', 'productions', 'people', 'works', 'posts']
    );

    foreach ($taxonomyRegistry->all() as $taxonomy) {
        if (!$taxonomy->hasArchive) {
            continue;
        }

        if (in_array($taxonomy->urlPrefix, $reservedPrefixes, true)) {
            throw new RuntimeException(sprintf(
                'Taxonomy "%s" cannot serve term archives under "/%s": a content type already uses that URL prefix.',
                $taxonomy->name,
                $taxonomy->urlPrefix
            ));
        }

        $app->get('/' . $taxonomy->urlPrefix . '/{slug}', function (
            Request $request,
            Response $response,
            array $args
        ) use (
            $container,
            $resolver,
            $taxonomy
        ) {
            /** @var TaxonomyDefinition $taxonomy */
            /** @var TermRepository $repository */
            $repository = $container->get(TermRepository::class);
            $term = $repository->fetchBySlugInTaxonomy($taxonomy->name, $args['slug']);

            if (!$term) {
                $response->getBody()->write('Term not found');

                return $response->withStatus(404);
            }

            $term = apply_filters('theatrecms/term', $term, $request, $args);

            /** @var TermArchiveQuery $archive */
            $archive = $container->get(TermArchiveQuery::class);
            $itemsByType = apply_filters(
                'theatrecms/term_archive_items',
                $archive->itemsFor($term, $taxonomy),
                $term,
                $request
            );

            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            return $resolver->renderTerm($twig, $response, $term, $taxonomy, $itemsByType);
        })->add(new RequireTwigMiddleware($container));
    }
}
