<?php

namespace TheatreCMS\Models;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;

/**
 * A single generated thumbnail size for a Media image, produced by
 * ImageVariantGenerator from a registered ImageSizeRegistry entry.
 */
#[Entity]
#[Table(name: 'media_variants')]
#[UniqueConstraint(name: 'media_variants_media_size_unique', columns: ['media_id', 'size_name'])]
class MediaVariant
{
    #[Id, Column(type: 'integer'), GeneratedValue(strategy: 'AUTO')]
    private int $id;

    #[ManyToOne(targetEntity: Media::class, inversedBy: 'variants')]
    #[JoinColumn(name: 'media_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Media $media;

    #[Column(name: 'size_name', type: 'string', length: 50, nullable: false)]
    private string $sizeName;

    #[Column(name: 'url', type: 'string', length: 255, nullable: false)]
    private string $url;

    #[Column(name: 'width', type: 'integer', nullable: false)]
    private int $width;

    #[Column(name: 'height', type: 'integer', nullable: false)]
    private int $height;

    public function __construct(Media $media, string $sizeName, string $url, int $width, int $height)
    {
        $this->media = $media;
        $this->sizeName = $sizeName;
        $this->url = $url;
        $this->width = $width;
        $this->height = $height;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getMedia(): Media
    {
        return $this->media;
    }

    public function getSizeName(): string
    {
        return $this->sizeName;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function setWidth(int $width): self
    {
        $this->width = $width;

        return $this;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function setHeight(int $height): self
    {
        $this->height = $height;

        return $this;
    }
}
