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
use TheatreCMS\Enums\ContentStatus;
use TheatreCMS\Traits\HasContentStatus;
use TheatreCMS\Traits\HasFeaturedImage;
use TheatreCMS\Traits\HasModifiedTimestamp;
use TheatreCMS\Traits\HasTimestamps;
use TheatreCMS\Helpers\Args;

/**
 * A single item of a registered custom content type (see `register_content_type()`).
 * Every custom type shares the one `entries` table, keyed by `content_type`, so
 * registering a type needs no schema change.
 *
 * Deliberately does not extend ModelBase: its slug column is unique across the whole
 * table, whereas entry slugs only need to be unique within their content type.
 */
#[HasLifecycleCallbacks]
#[Entity]
#[Table(name: 'entries')]
#[UniqueConstraint(name: 'uniq_entries_type_slug', columns: ['content_type', 'slug'])]
#[Index(name: 'idx_entries_type_status', columns: ['content_type', 'status'])]
class Entry
{
    use HasContentStatus;
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

    #[Column(type: 'text', nullable: true)]
    private ?string $excerpt = null;

    public function __construct(array $args)
    {
        $args = Args::parse($args, $this->getArgDefaults());

        $this->contentType = $args['contentType'];
        $this->title = $args['title'];
        $this->slug = $args['slug'];
        $this->status = $args['status'];
        $this->createdAt = $args['createdAt'];
        $this->modifiedAt = $args['modifiedAt'];

        if ($args['status'] === ContentStatus::PUBLISHED) {
            $this->publishedAt = new DateTimeImmutable();
        }
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

    public function getExcerpt(): ?string
    {
        return apply_filters('get_the_excerpt', $this->excerpt);
    }

    public function setExcerpt(?string $excerpt): self
    {
        $this->excerpt = $excerpt;

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
