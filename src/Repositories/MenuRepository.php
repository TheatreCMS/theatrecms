<?php

namespace TheatreCMS\Repositories;

use Doctrine\ORM\QueryBuilder;
use TheatreCMS\Enums\MenuItemType;
use TheatreCMS\Models\Menu;
use TheatreCMS\Models\MenuItem;

class MenuRepository extends BaseRepository
{
    protected string $entityClass = Menu::class;

    public function create(array $args): Menu
    {
        $args = array_merge([
            'name' => null,
            'location' => null,
        ], $args);

        if (empty($args['name'])) {
            throw new \InvalidArgumentException('Name is required.');
        }

        $location = !empty($args['location']) ? $args['location'] : null;

        if ($location !== null && $this->isLocationTaken($location)) {
            throw new \InvalidArgumentException('That location is already assigned to another menu.');
        }

        $menu = new Menu($args['name']);
        $menu->setLocation($location);

        $this->em->persist($menu);
        $this->em->flush();

        return $menu;
    }

    public function findByLocation(string $location): ?Menu
    {
        return $this->em->getRepository(Menu::class)->findOneBy(['location' => $location]);
    }

    public function isLocationTaken(string $location, ?int $excludeMenuId = null): bool
    {
        $existing = $this->findByLocation($location);

        if (!$existing) {
            return false;
        }

        return $excludeMenuId === null || $existing->getId() !== $excludeMenuId;
    }

    /**
     * @return array<int, Menu>
     */
    public function fetchAllOrderedByName(): array
    {
        return $this->em->createQueryBuilder()
            ->select('m')
            ->from(Menu::class, 'm')
            ->orderBy('m.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    protected function applyListOrder(QueryBuilder $builder, string $alias): void
    {
        $builder->orderBy(sprintf('%s.name', $alias), 'ASC');
    }

    /**
     * Replaces a menu's name/location/items in one transaction. Items are
     * deleted and recreated wholesale (relying on orphanRemoval) rather than
     * diffed, since item ids aren't referenced anywhere else.
     *
     * Each row in $itemsPayload is expected to provide:
     *   clientId (string), id (int|null), parentClientId (string|null),
     *   position (int), label (string|null), linkType (string),
     *   targetId (int|null), customUrl (string|null)
     *
     * @param array<int, array<string, mixed>> $itemsPayload
     */
    public function saveTree(Menu $menu, string $name, ?string $location, array $itemsPayload): void
    {
        $this->em->wrapInTransaction(function () use ($menu, $name, $location, $itemsPayload) {
            $menu->setName($name);
            $menu->setLocation($location);
            $menu->touchModified();

            $menu->getItems()->clear();
            $this->em->flush();

            /** @var array<string, MenuItem> $itemsByClientId */
            $itemsByClientId = [];

            foreach ($itemsPayload as $row) {
                $item = new MenuItem($menu, MenuItemType::from($row['linkType']));
                $item->setLabel($row['label'] ?: null);
                $item->setTargetId($row['targetId'] !== null ? (int) $row['targetId'] : null);
                $item->setCustomUrl($row['customUrl'] ?: null);
                $item->setPosition((int) $row['position']);

                $menu->addItem($item);
                $this->em->persist($item);

                $itemsByClientId[$row['clientId']] = $item;
            }

            foreach ($itemsPayload as $row) {
                if (!empty($row['parentClientId']) && isset($itemsByClientId[$row['parentClientId']])) {
                    $itemsByClientId[$row['clientId']]->setParent($itemsByClientId[$row['parentClientId']]);
                }
            }

            $this->em->flush();
        });
    }
}
