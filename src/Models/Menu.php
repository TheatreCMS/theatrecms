<?php

namespace TheatreCMS\Models;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\OrderBy;
use Doctrine\ORM\Mapping\Table;

#[Entity, Table(name: 'menus')]
class Menu
{
    #[Id, Column(type: 'integer'), GeneratedValue(strategy: 'AUTO')]
    private int $id;

    #[Column(type: 'string', length: 255, nullable: false)]
    private string $name;

    /**
     * The theme-registered location slug (e.g. "primary") this menu is assigned to, if any.
     * Not a foreign key: locations are declared at runtime by the active theme's functions.php,
     * not stored in the database.
     */
    #[Column(type: 'string', length: 100, nullable: true, unique: true)]
    private ?string $location = null;

    #[Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[Column(name: 'modified_at', type: 'datetime_immutable')]
    private DateTimeImmutable $modifiedAt;

    #[OneToMany(targetEntity: MenuItem::class, mappedBy: 'menu', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[OrderBy(['position' => 'ASC'])]
    private Collection $items;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->items = new ArrayCollection();
        $this->createdAt = new DateTimeImmutable();
        $this->modifiedAt = new DateTimeImmutable();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): self
    {
        $this->location = $location;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getModifiedAt(): DateTimeImmutable
    {
        return $this->modifiedAt;
    }

    public function touchModified(): self
    {
        $this->modifiedAt = new DateTimeImmutable();

        return $this;
    }

    /**
     * @return Collection<int, MenuItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    /**
     * @return Collection<int, MenuItem> top-level items only (no parent)
     */
    public function getTopLevelItems(): Collection
    {
        return $this->items->filter(fn(MenuItem $item) => $item->getParent() === null);
    }

    public function addItem(MenuItem $item): self
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setMenu($this);
        }

        return $this;
    }
}
