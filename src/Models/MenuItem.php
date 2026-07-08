<?php

namespace TheatreCMS\Models;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\OrderBy;
use Doctrine\ORM\Mapping\Table;
use TheatreCMS\Enums\MenuItemType;

#[Entity, Table(name: 'menu_items')]
class MenuItem
{
    #[Id, Column(type: 'integer'), GeneratedValue(strategy: 'AUTO')]
    private int $id;

    #[ManyToOne(targetEntity: Menu::class, inversedBy: 'items')]
    #[JoinColumn(name: 'menu_id', referencedColumnName: 'id', nullable: false)]
    private Menu $menu;

    #[ManyToOne(targetEntity: MenuItem::class, inversedBy: 'children')]
    #[JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?MenuItem $parent = null;

    #[OneToMany(targetEntity: MenuItem::class, mappedBy: 'parent', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[OrderBy(['position' => 'ASC'])]
    private Collection $children;

    #[Column(type: 'integer', options: ['default' => 0])]
    private int $position = 0;

    /**
     * Optional label override. When null, the label is resolved from the linked
     * entity (Page/Post/Production title) at render time. Required for CUSTOM links.
     */
    #[Column(name: 'label_override', type: 'string', length: 255, nullable: true)]
    private ?string $label = null;

    #[Column(name: 'link_type', type: 'string', length: 32, nullable: false, enumType: MenuItemType::class)]
    private MenuItemType $linkType;

    /**
     * Soft reference to a Page/Post/Production id, depending on $linkType.
     * Not a Doctrine association: Doctrine has no first-class polymorphic
     * association without an extra package, so this is a plain integer
     * resolved manually by MenuItemResolver.
     */
    #[Column(name: 'target_id', type: 'integer', nullable: true)]
    private ?int $targetId = null;

    #[Column(name: 'custom_url', type: 'string', length: 2048, nullable: true)]
    private ?string $customUrl = null;

    #[Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[Column(name: 'modified_at', type: 'datetime_immutable')]
    private DateTimeImmutable $modifiedAt;

    public function __construct(Menu $menu, MenuItemType $linkType)
    {
        $this->menu = $menu;
        $this->linkType = $linkType;
        $this->children = new ArrayCollection();
        $this->createdAt = new DateTimeImmutable();
        $this->modifiedAt = new DateTimeImmutable();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getMenu(): Menu
    {
        return $this->menu;
    }

    public function setMenu(Menu $menu): self
    {
        $this->menu = $menu;

        return $this;
    }

    public function getParent(): ?MenuItem
    {
        return $this->parent;
    }

    public function setParent(?MenuItem $parent): self
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return Collection<int, MenuItem>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getLinkType(): MenuItemType
    {
        return $this->linkType;
    }

    public function setLinkType(MenuItemType $linkType): self
    {
        $this->linkType = $linkType;

        return $this;
    }

    public function getTargetId(): ?int
    {
        return $this->targetId;
    }

    public function setTargetId(?int $targetId): self
    {
        $this->targetId = $targetId;

        return $this;
    }

    public function getCustomUrl(): ?string
    {
        return $this->customUrl;
    }

    public function setCustomUrl(?string $customUrl): self
    {
        $this->customUrl = $customUrl;

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
}
