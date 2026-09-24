<?php

namespace TheatreCMS\Controllers;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use TheatreCMS\Auth\AuthorizationService;
use TheatreCMS\Models\Term;
use TheatreCMS\Repositories\TermRelationshipRepository;
use TheatreCMS\Repositories\TermRepository;
use TheatreCMS\Taxonomy\TaxonomyDefinition;
use TheatreCMS\Taxonomy\TaxonomyRegistry;

/**
 * Admin screens for managing the terms of one registered taxonomy, at /admin/taxonomies/{taxonomy}.
 *
 * The required capability varies per taxonomy (TaxonomyDefinition::$capability), so it is checked
 * here rather than by route-group middleware.
 *
 * @extends BaseController<TermRepository>
 */
class TermController extends BaseController
{
    private const SORTABLE_COLUMNS = ['name', 'slug'];

    public function __construct(
        TermRepository $repository,
        Twig $twig,
        private readonly TermRelationshipRepository $relationships,
        private readonly TaxonomyRegistry $taxonomies,
        private readonly AuthorizationService $authorization,
    ) {
        parent::__construct($repository, $twig);
    }

    public function index(Request $request, Response $response, array $args = []): Response
    {
        $taxonomy = $this->resolveTaxonomy($args);
        if ($taxonomy instanceof Response) {
            return $taxonomy;
        }

        $data = $this->buildListViewData($request, $taxonomy);

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/taxonomies/_list.html.twig', $data);
        }

        return $this->twig->render($response, 'admin/taxonomies/index.html.twig', $data);
    }

    public function store(Request $request, Response $response, array $args = []): Response
    {
        $taxonomy = $this->resolveTaxonomy($args);
        if ($taxonomy instanceof Response) {
            return $taxonomy;
        }

        $data = $this->parseArgs((array) $request->getParsedBody(), ['name' => '', 'slug' => '', 'description' => '']);

        try {
            $term = $this->repository->create([
                'taxonomy' => $taxonomy->name,
                'name' => (string) $data['name'],
                'slug' => (string) $data['slug'],
                'description' => trim((string) $data['description']) ?: null,
            ]);
        } catch (InvalidArgumentException $e) {
            if ($request->getHeaderLine('HX-Request')) {
                return $this->freshAlertResponse($response, 'error', $e->getMessage())
                    ->withHeader('HX-Retarget', '#term-form-errors')
                    ->withHeader('HX-Reswap', 'innerHTML');
            }

            return $response->withStatus(400);
        }

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/taxonomies/_created.html.twig', array_merge(
                $this->buildListViewData($request, $taxonomy),
                ['message' => sprintf('%s "%s" added.', $taxonomy->singularLabel, $term->getName())]
            ));
        }

        return $response->withHeader('Location', $this->basePath($taxonomy))->withStatus(302);
    }

    public function edit(Request $request, Response $response, array $args = []): Response
    {
        $taxonomy = $this->resolveTaxonomy($args);
        if ($taxonomy instanceof Response) {
            return $taxonomy;
        }

        $term = $this->resolveTerm($taxonomy, $args);
        if ($term === null) {
            return $response->withStatus(404);
        }

        return $this->twig->render($response, 'admin/taxonomies/edit.html.twig', [
            'taxonomy' => $taxonomy,
            'term' => $term,
            'basePath' => $this->basePath($taxonomy),
        ]);
    }

    public function update(Request $request, Response $response, array $args = []): Response
    {
        $taxonomy = $this->resolveTaxonomy($args);
        if ($taxonomy instanceof Response) {
            return $taxonomy;
        }

        $term = $this->resolveTerm($taxonomy, $args);
        if ($term === null) {
            if ($request->getHeaderLine('HX-Request')) {
                $message = sprintf('%s not found.', $taxonomy->singularLabel);
                return $this->freshAlertResponse($response, 'error', $message);
            }
            return $response->withStatus(404);
        }

        $data = $this->parseArgs((array) $request->getParsedBody(), ['name' => '', 'slug' => '', 'description' => '']);

        try {
            $this->repository->updateTerm($term, [
                'name' => (string) $data['name'],
                'slug' => (string) $data['slug'],
                'description' => (string) $data['description'],
            ]);
        } catch (InvalidArgumentException $e) {
            if ($request->getHeaderLine('HX-Request')) {
                return $this->freshAlertResponse($response, 'error', $e->getMessage());
            }
            return $response->withStatus(400);
        }

        if ($request->getHeaderLine('HX-Request')) {
            // The slug may have been normalized or suffixed, so show the stored value.
            return $this->twig->render($response, 'admin/taxonomies/_saved.html.twig', [
                'taxonomy' => $taxonomy,
                'term' => $term,
            ]);
        }

        return $response->withHeader('Location', $this->basePath($taxonomy))->withStatus(302);
    }

    public function destroy(Request $request, Response $response, array $args = []): Response
    {
        $taxonomy = $this->resolveTaxonomy($args);
        if ($taxonomy instanceof Response) {
            return $taxonomy;
        }

        $term = $this->resolveTerm($taxonomy, $args);
        if ($term !== null) {
            $this->repository->delete($term);
        }

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render(
                $response,
                'admin/taxonomies/_list.html.twig',
                $this->buildListViewData($request, $taxonomy)
            );
        }

        return $this->buildListRedirect($response, $request, $this->basePath($taxonomy))->withStatus(302);
    }

    /**
     * The registered taxonomy named in the route, or a 404 (unknown) / 403 (not permitted) response.
     *
     * @param array<string, mixed> $args
     */
    private function resolveTaxonomy(array $args): TaxonomyDefinition|Response
    {
        $taxonomy = $this->taxonomies->get((string) ($args['taxonomy'] ?? ''));

        if ($taxonomy === null) {
            return (new \Slim\Psr7\Response())->withStatus(404);
        }

        if (!$this->authorization->can($taxonomy->capability)) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write('Forbidden');
            return $response->withStatus(403);
        }

        return $taxonomy;
    }

    /**
     * The term with the route's id, or null if it doesn't exist or belongs to another taxonomy.
     *
     * @param array<string, mixed> $args
     */
    private function resolveTerm(TaxonomyDefinition $taxonomy, array $args): ?Term
    {
        $term = $this->repository->fetch((int) ($args['id'] ?? 0));

        return $term instanceof Term && $term->getTaxonomy() === $taxonomy->name ? $term : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildListViewData(Request $request, TaxonomyDefinition $taxonomy): array
    {
        [$search, $sort, $direction] = $this->resolveListQuery($request, self::SORTABLE_COLUMNS);

        return $this->buildPaginatedViewData(
            $request,
            $this->repository,
            'terms',
            $this->basePath($taxonomy),
            [
                'taxonomy' => $taxonomy,
                'basePath' => $this->basePath($taxonomy),
                'counts' => $this->relationships->countsByTaxonomy($taxonomy->name),
            ],
            $search,
            $sort,
            $direction,
            ['taxonomy' => $taxonomy->name]
        );
    }

    private function basePath(TaxonomyDefinition $taxonomy): string
    {
        return '/admin/taxonomies/' . $taxonomy->name;
    }
}
