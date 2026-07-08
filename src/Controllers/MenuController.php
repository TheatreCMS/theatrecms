<?php

namespace TheatreCMS\Controllers;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use TheatreCMS\Enums\MenuItemType;
use TheatreCMS\Menus\MenuItemResolver;
use TheatreCMS\Repositories\MenuRepository;
use TheatreCMS\Repositories\PageRepository;
use TheatreCMS\Repositories\PostRepository;
use TheatreCMS\Repositories\ProductionRepository;
use TheatreCMS\Repositories\SeasonRepository;
use TheatreCMS\Theme\MenuLocationRegistry;

/**
 * @method MenuRepository repository()
 */
class MenuController extends BaseController
{
    public function __construct(
        MenuRepository $repository,
        EntityManagerInterface $em,
        Twig $twig,
        private readonly MenuLocationRegistry $locationRegistry,
        private readonly MenuItemResolver $resolver,
        private readonly PageRepository $pageRepository,
        private readonly PostRepository $postRepository,
        private readonly ProductionRepository $productionRepository,
        private readonly SeasonRepository $seasonRepository
    ) {
        $this->repository = $repository;
        $this->entityManager = $em;
        $this->twig = $twig;
    }

    public function index(Request $request, Response $response, array $args = []): Response
    {
        return $this->twig->render($response, 'admin/menus/index.html.twig', [
            'menus' => $this->repository->fetchAllOrderedByName(),
            'locations' => $this->locationRegistry->all(),
        ]);
    }

    public function create(Request $request, Response $response, array $args = []): Response
    {
        return $this->twig->render($response, 'admin/menus/create.html.twig', [
            'locations' => $this->locationRegistry->all(),
        ]);
    }

    public function store(Request $request, Response $response, array $args = []): Response
    {
        $data = $request->getParsedBody();

        if (empty($data)) {
            return $response->withStatus(400);
        }

        try {
            $menu = $this->repository->create([
                'name' => $data['name'] ?? null,
                'location' => $data['location'] ?? null,
            ]);
        } catch (\InvalidArgumentException $e) {
            $response->getBody()->write($e->getMessage());
            return $response->withStatus(400);
        }

        return $response->withStatus(302)->withHeader('Location', '/admin/menus/edit/' . $menu->getId());
    }

    public function edit(Request $request, Response $response, array $args = []): Response
    {
        $menu = $this->repository->fetch((int) $args['id']);

        if (!$menu) {
            return $response->withStatus(404);
        }

        return $this->twig->render($response, 'admin/menus/edit.html.twig', [
            'menu' => $menu,
            'menuItemsJson' => json_encode(
                $this->buildTreeForEditor($menu->getTopLevelItems()->toArray()),
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            ),
            'locations' => $this->locationRegistry->all(),
            'pages' => $this->pageRepository->fetchAll(),
            'posts' => $this->postRepository->fetchAll(),
            'productions' => $this->productionRepository->fetchAll(),
            'seasons' => $this->seasonRepository->fetchAll(),
        ]);
    }

    public function saveTree(Request $request, Response $response, array $args = []): Response
    {
        $isHtmx = (bool) $request->getHeaderLine('HX-Request');
        $menu = $this->repository->fetch((int) $args['id']);

        if (!$menu) {
            return $response->withStatus(404);
        }

        $data = $request->getParsedBody();
        $name = trim((string) ($data['name'] ?? ''));
        $location = !empty($data['location']) ? (string) $data['location'] : null;

        if ($name === '') {
            return $this->alertOrStatus($response, $isHtmx, 'error', 'Name is required.', 400);
        }

        if ($location !== null && $this->repository->isLocationTaken($location, $menu->getId())) {
            return $this->alertOrStatus($response, $isHtmx, 'error', 'That location is already assigned to another menu.', 400);
        }

        $rows = json_decode((string) ($data['items'] ?? '[]'), true);

        if (!is_array($rows)) {
            return $this->alertOrStatus($response, $isHtmx, 'error', 'Malformed menu item data.', 400);
        }

        try {
            $rows = $this->validateItemRows($rows);
        } catch (\InvalidArgumentException $e) {
            return $this->alertOrStatus($response, $isHtmx, 'error', $e->getMessage(), 400);
        }

        $this->repository->saveTree($menu, $name, $location, $rows);

        if ($isHtmx) {
            return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                'type' => 'success', 'message' => 'Menu saved successfully.',
            ]);
        }

        return $response->withStatus(302)->withHeader('Location', '/admin/menus/edit/' . $menu->getId());
    }

    public function destroy(Request $request, Response $response, array $args = []): Response
    {
        $menu = $this->repository->fetch((int) $args['id']);

        if ($menu) {
            $this->repository->delete($menu);
        }

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/menus/_list.html.twig', [
                'menus' => $this->repository->fetchAllOrderedByName(),
                'locations' => $this->locationRegistry->all(),
            ]);
        }

        return $response->withStatus(302)->withHeader('Location', '/admin/menus');
    }

    /**
     * @param array<int, \TheatreCMS\Models\MenuItem> $items
     * @return array<int, array<string, mixed>>
     */
    private function buildTreeForEditor(array $items): array
    {
        $tree = [];

        foreach ($items as $item) {
            $tree[] = [
                'id' => $item->getId(),
                'label' => $this->resolver->resolveLabel($item),
                'sourceTitle' => $this->resolver->resolveSourceTitle($item),
                'linkType' => $item->getLinkType()->value,
                'linkTypeLabel' => $item->getLinkType()->label(),
                'targetId' => $item->getTargetId(),
                'customUrl' => $item->getCustomUrl(),
                'orphaned' => $this->resolver->resolveUrl($item) === null,
                'children' => $this->buildTreeForEditor($item->getChildren()->toArray()),
            ];
        }

        return $tree;
    }

    /**
     * Validates and normalizes the flat item payload posted from the tree editor.
     * Rejects rows with invalid link types, missing required fields, or parent
     * cycles (defense in depth — the UI shouldn't be able to produce these).
     *
     * @param array<int, mixed> $rows
     * @return array<int, array<string, mixed>>
     */
    private function validateItemRows(array $rows): array
    {
        $normalized = [];
        $clientIds = [];

        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['clientId'])) {
                throw new \InvalidArgumentException('Malformed menu item data.');
            }

            $linkTypeValue = (string) ($row['linkType'] ?? '');
            $linkType = MenuItemType::tryFrom($linkTypeValue);

            if (!$linkType) {
                throw new \InvalidArgumentException('Invalid menu item link type.');
            }

            $customUrl = !empty($row['customUrl']) ? trim((string) $row['customUrl']) : null;
            $targetId = isset($row['targetId']) && $row['targetId'] !== '' ? (int) $row['targetId'] : null;

            if ($linkType === MenuItemType::CUSTOM) {
                if ($customUrl === null || $customUrl === '') {
                    throw new \InvalidArgumentException('Custom links require a URL.');
                }

                $scheme = parse_url($customUrl, PHP_URL_SCHEME);

                if ($scheme === false) {
                    throw new \InvalidArgumentException('Custom link URL is invalid.');
                }

                if ($scheme !== null && !in_array(strtolower($scheme), ['http', 'https', 'mailto', 'tel'], true)) {
                    throw new \InvalidArgumentException('Custom link URLs must be relative or use http(s), mailto, or tel.');
                }

                if ($scheme === null && str_starts_with($customUrl, '//')) {
                    throw new \InvalidArgumentException('Protocol-relative URLs (starting with "//") are not allowed.');
                }
            }

            if ($customUrl !== null) {
                $scheme = strtolower((string) parse_url($customUrl, PHP_URL_SCHEME));
                if (in_array($scheme, ['javascript', 'data'], true)) {
                    throw new \InvalidArgumentException('Custom link URL scheme is not allowed.');
                }
            }

            // Pages have no archive/listing page, so they must always link to a
            // specific page. Post/Production/Season items with no targetId are
            // valid — that's how they link to their type's archive instead.
            if ($linkType === MenuItemType::PAGE && !$targetId) {
                throw new \InvalidArgumentException('Pages must link to a specific page.');
            }

            $normalized[] = [
                'clientId' => (string) $row['clientId'],
                'id' => isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null,
                'parentClientId' => !empty($row['parentClientId']) ? (string) $row['parentClientId'] : null,
                'position' => (int) ($row['position'] ?? 0),
                'label' => !empty($row['label']) ? (string) $row['label'] : null,
                'linkType' => $linkType->value,
                'targetId' => $targetId,
                'customUrl' => $customUrl,
            ];

            $clientIds[(string) $row['clientId']] = true;
        }

        $this->assertNoParentCycles($normalized, $clientIds);

        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, bool> $clientIds
     */
    private function assertNoParentCycles(array $rows, array $clientIds): void
    {
        $parentByClientId = [];
        foreach ($rows as $row) {
            $parentByClientId[$row['clientId']] = $row['parentClientId'];
        }

        foreach ($rows as $row) {
            if ($row['parentClientId'] !== null && !isset($clientIds[$row['parentClientId']])) {
                throw new \InvalidArgumentException('Menu item references an unknown parent.');
            }

            $visited = [];
            $current = $row['clientId'];

            while ($parentByClientId[$current] !== null) {
                $current = $parentByClientId[$current];

                if (isset($visited[$current])) {
                    throw new \InvalidArgumentException('Menu items cannot be nested in a cycle.');
                }

                $visited[$current] = true;
            }
        }
    }

    private function alertOrStatus(Response $response, bool $isHtmx, string $type, string $message, int $status): Response
    {
        if ($isHtmx) {
            return $this->twig->render($response->withStatus($status), 'admin/partials/_alert.html.twig', [
                'type' => $type, 'message' => $message,
            ]);
        }

        $response->getBody()->write($message);
        return $response->withStatus($status);
    }
}
