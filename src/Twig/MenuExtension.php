<?php

namespace TheatreCMS\Twig;

use TheatreCMS\Menus\MenuItemResolver;
use TheatreCMS\Models\MenuItem;
use TheatreCMS\Repositories\MenuRepository;
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
        return [
            new TwigFunction('render_menu', [$this, 'renderMenu'], ['is_safe' => ['html'], 'needs_environment' => true]),
            new TwigFunction('get_menu', [$this, 'getMenuTree']),
        ];
    }

    public function renderMenu(\Twig\Environment $env, string $location, array $options = []): Markup
    {
        $tree = $this->getMenuTree($location);

        if ($tree === null) {
            return new Markup('', 'UTF-8');
        }

        $html = $env->render('@core/partials/_menu_items.html.twig', [
            'items' => $tree,
            'options' => $options,
        ]);

        return new Markup($html, 'UTF-8');
    }

    /**
     * @return array<int, array<string, mixed>>|null nested {label, url, children} nodes, or null if no menu is assigned to this location
     */
    public function getMenuTree(string $location): ?array
    {
        $menu = $this->menuRepository->findByLocation($location);

        if (!$menu) {
            return null;
        }

        return $this->resolveItems($menu->getTopLevelItems()->toArray());
    }

    /**
     * @param array<int, MenuItem> $items
     * @return array<int, array<string, mixed>>
     */
    private function resolveItems(array $items): array
    {
        $resolved = [];

        foreach ($items as $item) {
            $url = $this->resolver->resolveUrl($item);

            if ($url === null) {
                continue;
            }

            $resolved[] = [
                'label' => $this->resolver->resolveLabel($item),
                'url' => $url,
                'children' => $this->resolveItems($item->getChildren()->toArray()),
            ];
        }

        return $resolved;
    }
}
