<?php

namespace Clubdeuce\TheatreCMS\Models;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[Entity, Table(name: 'venues')]
class Venue
{
    #[Id, Column(type: 'integer'), GeneratedValue(strategy: 'AUTO')]
    private int $id = 0;

    #[Column(type: 'string', nullable: false)]
    private string $name;

    #[Column(type: 'string', nullable: false)]
    private string $address;

    #[Column(type: 'string', nullable: false)]
    private string $city;

    #[Column(type: 'string', nullable: false)]
    private string $state;

    #[Column(type: 'string', nullable: false)]
    private string $postcode;

    #[Column(type: 'string', nullable: false)]
    private string $country;

    #[Column(type: 'integer', nullable: true)]
    private ?int $capacity;

    #[Column(type: 'text', nullable: true)]
    private ?string $description;

    #[Column(name: 'accessibility_info', type: 'text', nullable: true)]
    private ?string $accessibilityInfo;

    #[Column(name: 'website_url', type: 'string', nullable: true)]
    private ?string $websiteUrl;

    #[Column(name: 'map_url', type: 'string', nullable: true)]
    private ?string $mapUrl;

    public function __construct(string $name, string $address, string $city, string $state, string $postcode, string $country)
    {
        $this->name = $name;
        $this->address = $address;
        $this->city = $city;
        $this->state = $state;
        $this->postcode = $postcode;
        $this->country = $country;

        // Initialize nullable fields to null
        $this->capacity = null;
        $this->description = null;
        $this->accessibilityInfo = null;
        $this->websiteUrl = null;
        $this->mapUrl = null;
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

    public function getAddress(): string
    {
        return $this->address;
    }

    public function setAddress(string $address): self
    {
        $this->address = $address;
        return $this;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function setCity(string $city): self
    {
        $this->city = $city;
        return $this;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function setState(string $state): self
    {
        $this->state = $state;
        return $this;
    }

    public function getPostcode(): string
    {
        return $this->postcode;
    }

    public function setPostcode(string $postcode): self
    {
        $this->postcode = $postcode;
        return $this;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function setCountry(string $country): self
    {
        $this->country = $country;
        return $this;
    }

    public function getCapacity(): ?int
    {
        return $this->capacity;
    }

    public function setCapacity(?int $capacity): self
    {
        $this->capacity = $capacity;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getAccessibilityInfo(): ?string
    {
        return $this->accessibilityInfo;
    }

    public function setAccessibilityInfo(?string $accessibilityInfo): self
    {
        $this->accessibilityInfo = $accessibilityInfo;
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

    public function getMapUrl(): ?string
    {
        return $this->mapUrl;
    }

    public function setMapUrl(?string $mapUrl): self
    {
        $this->mapUrl = $mapUrl;
        return $this;
    }
}
