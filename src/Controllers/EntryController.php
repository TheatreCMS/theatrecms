<?php

namespace TheatreCMS\Controllers;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use TheatreCMS\ContentType\ContentTypeDefinition;
use TheatreCMS\Enums\ContentStatus;
use TheatreCMS\Models\Entry;
use TheatreCMS\Repositories\ContentMetaRepository;
use TheatreCMS\Repositories\EntryRepository;
use TheatreCMS\Repositories\TermRelationshipRepository;
use TheatreCMS\Theme\TemplateResolver;
use TheatreCMS\Traits\AssignsTaxonomyTerms;
use TheatreCMS\Traits\HandlesContentImages;
use TheatreCMS\Traits\RendersPublicContent;

/**
 * Admin screens for the entries of one content type. Each instance is built for a single
 * `ContentTypeDefinition` (see `app/routes.php`), which scopes the repository, the URLs and
 * the labels; the form shows the editor, excerpt and featured image only when the type
 * supports them. Public pages come from RendersPublicContent.
 *
 * @extends BaseController<EntryRepository>
 */
class EntryController extends BaseController implements PublicContentController
{
    use HandlesContentImages;
    use AssignsTaxonomyTerms;
    use RendersPublicContent;

    private const SORTABLE_COLUMNS = ['title', 'publishedAt'];

    public function __construct(
        EntryRepository $repository,
        EntityManagerInterface $em,
        Twig $twig,
        private readonly ContentTypeDefinition $definition,
        private readonly ContentMetaRepository $contentMeta,
        private readonly TermRelationshipRepository $termRelationships,
        private readonly TemplateResolver $templateResolver,
    ) {
        parent::__construct($repository->forType($definition->name), $twig, $em);
    }

    public function contentType(): string
    {
        return $this->definition->name;
    }

    protected function routePrefix(): string
    {
        return $this->definition->urlPrefix;
    }

    protected function termRelationships(): TermRelationshipRepository
    {
        return $this->termRelationships;
    }

    protected function templateResolver(): TemplateResolver
    {
        return $this->templateResolver;
    }

    public function index(Request $request, Response $response, array $args = []): Response
    {
        [$search, $sort, $direction] = $this->resolveListQuery($request, self::SORTABLE_COLUMNS);
        $data = $this->buildPaginatedViewData(
            $request,
            $this->repository,
            'entries',
            $this->basePath(),
            $this->viewData(['statuses' => ContentStatus::labels()]),
            $search,
            $sort,
            $direction
        );

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/entries/_list.html.twig', $data);
        }

        return $this->twig->render($response, 'admin/entries/index.html.twig', $data);
    }

    public function create(Request $request, Response $response, array $args = []): Response
    {
        return $this->twig->render($response, 'admin/entries/create.html.twig', $this->viewData([
            'statuses' => ContentStatus::labels(),
        ]));
    }

    public function edit(Request $request, Response $response, array $args = []): Response
    {
        $entry = $this->repository->fetch((int) $args['id']);

        if (!$entry) {
            return $response->withStatus(404);
        }

        return $this->twig->render($response, 'admin/entries/edit.html.twig', $this->viewData([
            'entry'    => $entry,
            'statuses' => ContentStatus::labels(),
        ]));
    }

    public function store(Request $request, Response $response, array $args = []): Response
    {
        $data = $request->getParsedBody();

        if (empty($data)) {
            return $this->errorResponse($request, $response, 400, 'create');
        }

        try {
            $entry = $this->repository->create([
                'title'   => $data['title'] ?? null,
                'status'  => $data['status'] ?? ContentStatus::DRAFT->value,
                'content' => $this->definition->supports('editor') ? ($data['content'] ?? null) : null,
                'excerpt' => $this->definition->supports('excerpt') ? ($data['excerpt'] ?? null) : null,
            ]);

            if ($this->definition->supports('featured_image')) {
                $this->applyFeaturedImage($entry, $data['featuredImageId'] ?? null);
            }
            $this->applyTerms($this->contentType(), $entry->getId(), $data);
            $this->entityManager->flush();
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($request, $response, 400, 'create');
        }

        if ($request->getHeaderLine('HX-Request')) {
            return $response->withHeader('HX-Redirect', $this->editUrl($entry));
        }

        return $response->withHeader('Location', $this->editUrl($entry))->withStatus(302);
    }

    public function update(Request $request, Response $response, array $args = []): Response
    {
        $data = $request->getParsedBody();

        if (empty($data)) {
            return $this->errorResponse($request, $response, 400, 'save');
        }

        $data = $this->parseArgs($data, [
            'entryId'         => 0,
            'title'           => null,
            'status'          => ContentStatus::DRAFT->value,
            'slug'            => null,
            'publishedAt'     => null,
            'content'         => null,
            'excerpt'         => null,
            'featuredImageId' => null,
        ]);

        $entry = $this->repository->fetch((int) $data['entryId']);

        if (!$entry) {
            return $this->errorResponse($request, $response, 404, 'save');
        }

        $status = ContentStatus::tryFrom((string) $data['status']);

        if (empty($data['title']) || $status === null) {
            return $this->errorResponse($request, $response, 400, 'save');
        }

        try {
            $entry->setTitle($data['title']);
            $entry->setStatus($status);

            if ($this->definition->supports('editor')) {
                $entry->setContent($data['content'] ?: null);
            }
            if ($this->definition->supports('excerpt')) {
                $entry->setExcerpt($data['excerpt'] ?: null);
            }
            if ($this->definition->supports('featured_image')) {
                $this->applyFeaturedImage($entry, $data['featuredImageId']);
            }

            $entry->setPublishedAt($this->resolvePublishedAt($entry, $status, $data['publishedAt']));

            if (!empty($data['slug'])) {
                $this->repository->updateSlug($entry, $data['slug']);
            }

            $this->applyTerms($this->contentType(), $entry->getId(), $data);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($request, $response, 400, 'save');
        }

        $entry->touchModified();
        $this->repository->update($entry);

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/entries/_saved.html.twig', $this->viewData([
                'entry' => $entry,
            ]));
        }

        return $response->withHeader('Location', $this->basePath())->withStatus(302);
    }

    public function destroy(Request $request, Response $response, array $args = []): Response
    {
        $entry = $this->repository->fetch((int) $args['id']);

        if ($entry) {
            try {
                $this->repository->delete($entry);
            } catch (\Exception $e) {
                trigger_error("Unable to delete {$this->definition->name} entry: {$e->getMessage()}");
            }
        }

        if ($request->getHeaderLine('HX-Request')) {
            $data = $this->buildPaginatedViewData(
                $request,
                $this->repository,
                'entries',
                $this->basePath(),
                $this->viewData(['statuses' => ContentStatus::labels()])
            );

            return $this->twig->render($response, 'admin/entries/_list.html.twig', $data);
        }

        return $this->buildListRedirect($response, $request, $this->basePath())->withStatus(302);
    }

    /**
     * An explicit date from the form wins; publishing an entry with no date stamps it now;
     * anything else clears it, so drafts carry no publish date.
     */
    private function resolvePublishedAt(Entry $entry, ContentStatus $status, ?string $publishedAt): ?DateTimeImmutable
    {
        if (!empty($publishedAt)) {
            return DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $publishedAt) ?: null;
        }

        if ($status === ContentStatus::PUBLISHED) {
            return $entry->getPublishedAt() ?? new DateTimeImmutable();
        }

        return null;
    }

    /**
     * An alert partial for HTMX requests, a bare status code otherwise.
     *
     * @param string $action 'create' or 'save', for the message
     */
    private function errorResponse(Request $request, Response $response, int $status, string $action): Response
    {
        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                'type'    => 'error',
                'message' => sprintf(
                    'Unable to %s %s. Please check your input.',
                    $action,
                    strtolower($this->definition->singularLabel)
                ),
            ]);
        }

        return $response->withStatus($status);
    }

    private function basePath(): string
    {
        return '/' . $this->definition->adminSlug();
    }

    private function editUrl(Entry $entry): string
    {
        return $this->basePath() . '/edit/' . $entry->getId();
    }

    /**
     * Adds the content type's definition as `content_type`, so the shared entry templates can
     * show its labels and build its URLs.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function viewData(array $data = []): array
    {
        return ['content_type' => $this->definition] + $data;
    }
}
