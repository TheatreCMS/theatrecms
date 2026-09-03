<?php

namespace TheatreCMS\Models;

use DateTimeImmutable;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

/**
 * A single uploaded file in the media library, shared across content types
 * (Production, Post, Season, Venue) via a `featured_image_id` foreign key.
 */
#[Entity, Table(name: 'images')]
class Image
{
    #[Id, Column(type: 'integer'), GeneratedValue(strategy: 'AUTO')]
    private int $id;

    #[Column(name: 'url', type: 'string', length: 255, nullable: false)]
    private string $url;

    #[Column(name: 'filename', type: 'string', length: 255, nullable: false)]
    private string $filename;

    #[Column(name: 'original_filename', type: 'string', length: 255, nullable: true)]
    private ?string $originalFilename = null;

    #[Column(name: 'mime_type', type: 'string', length: 100, nullable: true)]
    private ?string $mimeType = null;

    #[Column(name: 'size_bytes', type: 'integer', nullable: true)]
    private ?int $sizeBytes = null;

    #[Column(name: 'width', type: 'integer', nullable: true)]
    private ?int $width = null;

    #[Column(name: 'height', type: 'integer', nullable: true)]
    private ?int $height = null;

    #[Column(name: 'alt_text', type: 'string', length: 255, nullable: true)]
    private ?string $altText = null;

    #[Column(name: 'uploaded_at', type: 'datetime_immutable', nullable: false)]
    private DateTimeImmutable $uploadedAt;

    public function __construct(string $url, string $filename)
    {
        $this->url = $url;
        $this->filename = $filename;
        $this->uploadedAt = new DateTimeImmutable();
    }

    public function getId(): int
    {
        return $this->id;
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

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): self
    {
        $this->filename = $filename;

        return $this;
    }

    public function getOriginalFilename(): ?string
    {
        return $this->originalFilename;
    }

    public function setOriginalFilename(?string $originalFilename): self
    {
        $this->originalFilename = $originalFilename;

        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(?string $mimeType): self
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getSizeBytes(): ?int
    {
        return $this->sizeBytes;
    }

    public function setSizeBytes(?int $sizeBytes): self
    {
        $this->sizeBytes = $sizeBytes;

        return $this;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function setWidth(?int $width): self
    {
        $this->width = $width;

        return $this;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setHeight(?int $height): self
    {
        $this->height = $height;

        return $this;
    }

    public function getAltText(): ?string
    {
        return $this->altText;
    }

    public function setAltText(?string $altText): self
    {
        $this->altText = $altText;

        return $this;
    }

    public function getUploadedAt(): DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    public function setUploadedAt(DateTimeImmutable $uploadedAt): self
    {
        $this->uploadedAt = $uploadedAt;

        return $this;
    }
}
