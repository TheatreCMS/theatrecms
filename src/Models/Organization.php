<?php

namespace Clubdeuce\TheatreCMS\Models;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;
use JsonSerializable;

#[Entity, Table(name: 'organizations')]
class Organization implements JsonSerializable
{
    #[Id, Column(type: 'integer'), GeneratedValue(strategy: 'AUTO')]
    private int $id = 0;

    #[Column(type: 'string', nullable: false)]
    private string $name;

    #[Column(type: 'string', unique: true, nullable: false)]
    private string $slug;

    #[Column(name: 'mission_statement', type: 'text', nullable: true)]
    private ?string $missionStatement = null;

    #[Column(name: 'founded_year', type: 'integer', nullable: true)]
    private ?int $foundedYear = null;

    #[Column(name: 'logo_url', type: 'string', nullable: true)]
    private ?string $logoUrl = null;

    #[Column(name: 'website_url', type: 'string', nullable: true)]
    private ?string $websiteUrl = null;

    #[Column(name: 'social_links', type: 'json', nullable: true)]
    private ?array $socialLinks = null;

    #[Column(type: 'string', nullable: true)]
    private ?string $address = null;

    public function __construct(string $name = '', string $slug = '')
    {
        $this->name = $name;
        $this->slug = $slug;
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

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    public function getMissionStatement(): ?string
    {
        return $this->missionStatement;
    }

    public function setMissionStatement(?string $missionStatement): self
    {
        $this->missionStatement = $missionStatement;
        return $this;
    }

    public function getFoundedYear(): ?int
    {
        return $this->foundedYear;
    }

    public function setFoundedYear(?int $foundedYear): self
    {
        $this->foundedYear = $foundedYear;
        return $this;
    }

    public function getLogoUrl(): ?string
    {
        return $this->logoUrl;
    }

    public function setLogoUrl(?string $logoUrl): self
    {
        $this->logoUrl = $logoUrl;
        return $this;
    }

    public function getWebsiteUrl(): ?string
    {
        return $this->websiteUrl;
    }

    public function setWebsiteUrl(?string $websiteUrl): self
    {
        $this->websiteUrl = $websiteUrl;
        return $this;
    }

    public function getSocialLinks(): ?array
    {
        return $this->socialLinks;
    }

    public function setSocialLinks(?array $socialLinks): self
    {
        $this->socialLinks = $socialLinks;
        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): self
    {
        $this->address = $address;
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'slug' => $this->getSlug(),
            'missionStatement' => $this->getMissionStatement(),
            'foundedYear' => $this->getFoundedYear(),
            'logoUrl' => $this->getLogoUrl(),
            'websiteUrl' => $this->getWebsiteUrl(),
            'socialLinks' => $this->getSocialLinks(),
            'address' => $this->getAddress(),
        ];
    }
}
