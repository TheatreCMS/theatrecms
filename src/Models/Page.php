<?php

namespace TheatreCMS\Models;

use DateTimeImmutable;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;
use TheatreCMS\ContentType\HasContentType;
use TheatreCMS\ContentType\IsHierarchical;
use TheatreCMS\Enums\ContentStatus;
use TheatreCMS\Traits\HasContentStatus;
use TheatreCMS\Traits\HasModifiedTimestamp;
use TheatreCMS\Traits\HasParent;
use TheatreCMS\Traits\HasTimestamps;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Doctrine\ORM\Mapping\PrePersist;
use Doctrine\ORM\Mapping\PreUpdate;

/**
 * Pages are served at the site root and nest: `/about/board-of-directors` is the
 * `board-of-directors` page under `about`. Slugs are unique among siblings; parent_scope maps a
 * top-level page's null parent to zero so the database can enforce that rule at every level.
 */
#[HasLifecycleCallbacks]
#[Entity, Table(name: 'pages')]
#[UniqueConstraint(name: 'uniq_pages_parent_scope_slug', columns: ['parent_scope', 'slug'])]
class Page implements HasContentType, IsHierarchical
{
    use HasContentStatus;
    use HasParent;
    use HasTimestamps;
    use HasModifiedTimestamp;

    #[Id, Column(type: 'integer'), GeneratedValue(strategy: 'AUTO')]
    private int $id;

    #[Column(type: 'string', length: 255, nullable: false)]
    private string $title;

    #[Column(type: 'string', nullable: false)]
    private string $slug;

    #[Column(name: 'parent_scope', type: 'integer', nullable: false, options: ['default' => 0])]
    private int $parentScope = 0;

    #[Column(type: 'text', nullable: false)]
    private string $content;

    public function __construct(string $title, ContentStatus $status, string $content)
    {
        $this->title = $title;
        $this->status = $status;
        $this->content = $content;
        $this->createdAt = new DateTimeImmutable();
        $this->modifiedAt = new DateTimeImmutable();

        if ($status === ContentStatus::PUBLISHED) {
            $this->publishedAt = new DateTimeImmutable();
        }
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getContentType(): string
    {
        return 'page';
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    #[PrePersist]
    #[PreUpdate]
    public function syncParentScope(): void
    {
        $parent = $this->getParent();
        $scope = $parent !== null && isset($parent->id) ? $parent->id : 0;

        if ($this->parentScope !== $scope) {
            $this->parentScope = $scope;
        }
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }
}
