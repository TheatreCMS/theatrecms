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
use Doctrine\ORM\Mapping\Table;

/**
 * A single uploaded file in the media library (image, PDF, audio, or video),
 * shared across content types (Production, Post, Season, Venue) via a
 * `featured_image_id` foreign key.
 */
#[Entity, Table(name: 'media')]
class Media
{
    public const TYPE_IMAGE = 'image';
    public const TYPE_PDF = 'pdf';
    public const TYPE_AUDIO = 'audio';
    public const TYPE_VIDEO = 'video';
    public const TYPE_OTHER = 'other';

    public const ALL_TYPES = [
        self::TYPE_IMAGE,
        self::TYPE_PDF,
        self::TYPE_AUDIO,
        self::TYPE_VIDEO,
        self::TYPE_OTHER,
    ];

    #[Id, Column(type: 'integer'), GeneratedValue(strategy: 'AUTO')]
    private int $id;

    #[Column(name: 'media_type', type: 'string', length: 20, nullable: false)]
    private string $mediaType;

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

    /**
     * @var Collection<int, MediaVariant>
     */
    #[OneToMany(
        mappedBy: 'media',
        targetEntity: MediaVariant::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $variants;

    public function __construct(string $url, string $filename, string $mediaType)
    {
        $this->url = $url;
        $this->filename = $filename;
        $this->mediaType = $mediaType;
        $this->uploadedAt = new DateTimeImmutable();
        $this->variants = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getMediaType(): string
    {
        return $this->mediaType;
    }

    public function setMediaType(string $mediaType): self
    {
        $this->mediaType = $mediaType;

        return $this;
    }

    public function isImage(): bool
    {
        return $this->mediaType === self::TYPE_IMAGE;
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

    /**
     * @return Collection<int, MediaVariant>
     */
    public function getVariants(): Collection
    {
        return $this->variants;
    }

    /**
     * Adds a generated variant, replacing any existing one for the same size name.
     */
    public function addVariant(MediaVariant $variant): self
    {
        foreach ($this->variants as $key => $existing) {
            if ($existing->getSizeName() === $variant->getSizeName()) {
                $this->variants->set($key, $variant);

                return $this;
            }
        }

        $this->variants->add($variant);

        return $this;
    }

    /**
     * The URL of the named thumbnail size (see ImageSizeRegistry), falling
     * back to the original file when that size hasn't been generated for
     * this media.
     */
    public function getVariantUrl(string $sizeName): string
    {
        foreach ($this->variants as $variant) {
            if ($variant->getSizeName() === $sizeName) {
                return $variant->getUrl();
            }
        }

        return $this->url;
    }
}
