<?php

namespace TheatreCMS\Models;

use DateTimeImmutable;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;
use TheatreCMS\Enums\ContentStatus;
use TheatreCMS\Traits\HasContentStatus;
use TheatreCMS\Traits\HasModifiedTimestamp;
use TheatreCMS\Traits\HasTimestamps;

#[Entity, Table(name: 'posts')]
class Post extends ModelBase
{
    use HasContentStatus;
    use HasTimestamps;
    use HasModifiedTimestamp;

    #[Id, Column(type: 'integer'), GeneratedValue(strategy: 'AUTO')]
    private int $id;

    #[Column(type: 'string', length: 255, nullable: false)]
    private string $title;

    #[Column(type: 'text', nullable: false)]
    private string $content;

    #[ManyToOne(targetEntity: Image::class)]
    #[JoinColumn(name: 'featured_image_id', referencedColumnName: 'id', nullable: true)]
    private ?Image $featuredImage = null;

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

    public function getFeaturedImage(): ?Image
    {
        return $this->featuredImage;
    }

    public function setFeaturedImage(?Image $featuredImage): self
    {
        $this->featuredImage = $featuredImage;

        return $this;
    }

    public function getFeaturedImageUrl(): ?string
    {
        return $this->featuredImage?->getUrl();
    }

    public function hasFeaturedImage(): bool
    {
        return $this->featuredImage !== null;
    }
}
