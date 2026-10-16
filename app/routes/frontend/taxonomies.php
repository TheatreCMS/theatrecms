<?php

use TheatreCMS\Middleware\RequireTwigMiddleware;
use TheatreCMS\Repositories\TermRepository;
use TheatreCMS\Taxonomy\TaxonomyDefinition;
use TheatreCMS\Taxonomy\TaxonomyRegistry;
use TheatreCMS\Taxonomy\TermArchiveQuery;
use TheatreCMS\Theme\TemplateResolver;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Term archives: one `/{urlPrefix}/{term-slug}` route per taxonomy with `hasArchive` on.
 * Routes are registered per prefix rather than as a `/{taxonomy}/{slug}` wildcard, which
 * would collide with other two-segment routes like `/seasons/{slug}/{productionSlug}`. A prefix
 * clash with a content type is already rejected when the taxonomy registers (`UrlPrefixRegistry`).
 *
 * @var TemplateResolver $resolver
 */

if (isset($app)) {
    $container = $app->getContainer();

    /** @var TaxonomyRegistry $taxonomyRegistry */
    $taxonomyRegistry = $container->get(TaxonomyRegistry::class);

    foreach ($taxonomyRegistry->all() as $taxonomy) {
        if (!$taxonomy->hasArchive) {
            continue;
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
