<?php

namespace TheatreCMS\Models;

use DateTimeImmutable;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Index;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;
use TheatreCMS\ContentType\HasContentType;
use TheatreCMS\ContentType\IsHierarchical;
use TheatreCMS\Enums\ContentStatus;
use TheatreCMS\Traits\HasContentStatus;
use TheatreCMS\Traits\HasExcerpt;
use TheatreCMS\Traits\HasFeaturedImage;
use TheatreCMS\Traits\HasModifiedTimestamp;
use TheatreCMS\Traits\HasParent;
use TheatreCMS\Traits\HasTimestamps;
use TheatreCMS\Helpers\Args;

/**
 * A single item of a registered custom content type (see `register_content_type()`).
 * Every custom type shares the one `entries` table, keyed by `content_type`, so
 * registering a type needs no schema change.
 *
 * Deliberately does not extend ModelBase: its slug column is unique across the whole
 * table, whereas entry slugs only need to be unique within their content type.
 *
 * Entries of a hierarchical type (`hierarchical: true`) can have a parent entry of the same
 * type; for other types `parent_id` stays NULL.
 */
#[HasLifecycleCallbacks]
#[Entity]
#[Table(name: 'entries')]
#[UniqueConstraint(name: 'uniq_entries_type_slug', columns: ['content_type', 'slug'])]
#[Index(name: 'idx_entries_type_status', columns: ['content_type', 'status'])]
class Entry implements HasContentType, IsHierarchical
{
    use HasContentStatus;
    use HasParent;
    use HasExcerpt;
    use HasFeaturedImage;
    use HasTimestamps;
    use HasModifiedTimestamp;

    #[Id, Column(type: 'integer'), GeneratedValue(strategy: 'AUTO')]
    private int $id;

    /**
     * Registry key of the content type this entry belongs to. A free-form string rather
     * than an enum so theme/plugin-registered types need no code changes here.
     */
    #[Column(name: 'content_type', type: 'string', nullable: false)]
    private string $contentType;

    #[Column(type: 'string', nullable: false)]
    private string $title;

    #[Column(type: 'string', nullable: false)]
    private string $slug;

    /**
     * EditorJS block JSON.
     */
    #[Column(type: 'text', nullable: true)]
    private ?string $content = null;

    public function __construct(array $args)
    {
        $args = Args::parse($args, $this->getArgDefaults());

        $this->contentType = $args['contentType'];
        $this->title = $args['title'];
        $this->slug = $args['slug'];
        $this->status = $args['status'];
        $this->createdAt = $args['createdAt'];
        $this->modifiedAt = $args['modifiedAt'];
        $this->content = $args['content'];
        $this->excerpt = $args['excerpt'];
        $this->publishedAt = $args['publishedAt']
            ?? ($args['status'] === ContentStatus::PUBLISHED ? new DateTimeImmutable() : null);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getContentType(): string
    {
        return $this->contentType;
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

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getArgDefaults(): array
    {
        return apply_filters('entry_args_defaults', [
            'contentType' => '',
            'title' => '',
            'slug' => '',
            'status' => ContentStatus::DRAFT,
            'content' => null,
            'excerpt' => null,
            'createdAt' => new DateTimeImmutable(),
            'modifiedAt' => new DateTimeImmutable(),
            'publishedAt' => null,
        ]);
    }
}
