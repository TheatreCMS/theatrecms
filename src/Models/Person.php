<?php

namespace Clubdeuce\TheatreCMS\Models;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;
use JsonSerializable;

#[Entity, Table(name: 'people')]
class Person implements JsonSerializable
{
    #[Id, Column(type: 'integer'), GeneratedValue(strategy: 'AUTO')]
    private int $id;

    #[Column(type: 'string', unique: true, nullable: false)]
    private string $name;

    #[Column(type: 'text', nullable: true)]
    private string $biography;

    #[Column(name: 'headshot_url', type: 'string', nullable: true)]
    private string $headshotUrl;


    public function __construct(string $name, string $biography = "", string $headshotUrl = "")
    {
        $this->name = $name;
        $this->biography = $biography;
        $this->headshotUrl = $headshotUrl;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getBiography(): string
    {
        return $this->biography;
    }

    public function getHeadshotUrl(): string
    {
        return $this->headshotUrl;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function setBiography(string $biography): self
    {
        $this->biography = $biography;
        return $this;
    }

    public function setHeadshotUrl(string $headshotUrl): self
    {
        $this->headshotUrl = $headshotUrl;
        return $this;
    }

    /**
     * @return array<int|string>
     */
    public function jsonSerialize(): array
    {
        return [
            'id'          => $this->getId(),
            'name'        => $this->getName(),
            'biography'   => $this->getBiography(),
            'headshotUrl' => $this->getHeadshotUrl(),
        ];
    }
}
