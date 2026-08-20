<?php

namespace TheatreCMS\Models;

use DateTimeImmutable;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;
use TheatreCMS\Enums\ContentStatus;
use TheatreCMS\Traits\HasContentStatus;

#[Entity, Table(name: 'posts')]
class Post extends ModelBase
{
    use HasContentStatus;

    #[Id, Column(type: 'integer'), GeneratedValue(strategy: 'AUTO')]
    private int $id;

    #[Column(type: 'string', length: 255, nullable: false)]
    private string $title;

    #[Column(type: 'text', nullable: false)]
    private string $content;

    #[Column(name: 'featured_image_url', type: 'string', length: 255, nullable: true)]
    private ?string $featuredImageUrl = null;

    #[Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[Column(name: 'modified_at', type: 'datetime_immutable')]
    private DateTimeImmutable $modifiedAt;

    #[Column(name: 'published_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $publishedAt = null;

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

    public function getFeaturedImageUrl(): ?string
    {
        return $this->featuredImageUrl;
    }

    public function setFeaturedImageUrl(?string $featuredImageUrl): self
    {
        $this->featuredImageUrl = $featuredImageUrl;

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

    public function setModifiedAt(DateTimeImmutable $modifiedAt): self
    {
        $this->modifiedAt = $modifiedAt;

        return $this;
    }

    public function touchModified(): self
    {
        return $this->setModifiedAt(new DateTimeImmutable());
    }

    public function getPublishedAt(): ?DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?DateTimeImmutable $publishedAt): self
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }
}
