<?php

namespace TheatreCMS\Twig;

use TheatreCMS\Menus\MenuItemResolver;
use TheatreCMS\Models\MenuItem;
use TheatreCMS\Repositories\MenuRepository;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFunction;

class MenuExtension extends AbstractExtension
{
    public function __construct(
        private readonly MenuRepository $menuRepository,
        private readonly MenuItemResolver $resolver
    ) {
    }

    public function getFunctions(): array
    {
        // Both read the request path from the `current_path` template variable to mark the current item.
        return [
            new TwigFunction(
                'render_menu',
                fn(Environment $env, array $context, string $location, array $options = []): Markup
                    => $this->renderMenu($env, $location, $options, $context['current_path'] ?? null),
                ['is_safe' => ['html'], 'needs_environment' => true, 'needs_context' => true]
            ),
            new TwigFunction(
                'get_menu',
                fn(array $context, string $location): ?array
                    => $this->getMenuTree($location, $context['current_path'] ?? null),
                ['needs_context' => true]
            ),
        ];
    }

    public function renderMenu(
        Environment $env,
        string $location,
        array $options = [],
        ?string $currentPath = null
    ): Markup {
        $tree = $this->getMenuTree($location, $currentPath);

        if ($tree === null) {
            return new Markup('', 'UTF-8');
        }

        $html = $env->render('partials/_menu_items.html.twig', [
            'items' => $tree,
            'options' => $options,
        ]);

        return new Markup($html, 'UTF-8');
    }

    /**
     * Nodes are {label, url, current, currentAncestor, children}. `current` marks the item whose
     * URL is the page being viewed; `currentAncestor` marks every item above it. Only site-relative
     * URLs (the ones content items resolve to, or a custom link like `/tickets`) can be current.
     *
     * @return array<int, array<string, mixed>>|null nested nodes, or null if no menu is assigned to this location
     */
    public function getMenuTree(string $location, ?string $currentPath = null): ?array
    {
        $menu = $this->menuRepository->findByLocation($location);

        if (!$menu) {
            return null;
        }

        $currentPath = $currentPath !== null ? $this->normalizePath($currentPath) : null;

        return $this->resolveItems($menu->getTopLevelItems()->toArray(), $currentPath);
    }

    /**
     * @param array<int, MenuItem> $items
     * @return array<int, array<string, mixed>>
     */
    private function resolveItems(array $items, ?string $currentPath): array
    {
        $resolved = [];

        foreach ($items as $item) {
            $url = $this->resolver->resolveUrl($item);

            if ($url === null) {
                continue;
            }

            $children = $this->resolveItems($item->getChildren()->toArray(), $currentPath);

            $resolved[] = [
                'label' => $this->resolver->resolveLabel($item),
                'url' => $url,
                'current' => $currentPath !== null && $this->sitePath($url) === $currentPath,
                'currentAncestor' => $this->containsCurrent($children),
                'children' => $children,
            ];
        }

        return $resolved;
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     */
    private function containsCurrent(array $nodes): bool
    {
        foreach ($nodes as $node) {
            if ($node['current'] || $node['currentAncestor']) {
                return true;
            }
        }

        return false;
    }

    /**
     * The normalized path of a site-relative URL, or null for anything else (another host,
     * mailto:, tel:, a bare fragment).
     */
    private function sitePath(string $url): ?string
    {
        $parts = parse_url($url);

        if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || !str_starts_with($url, '/')) {
            return null;
        }

        return $this->normalizePath($parts['path'] ?? '/');
    }

    private function normalizePath(string $path): string
    {
        $path = rtrim($path, '/');

        return $path === '' ? '/' : $path;
    }
}
