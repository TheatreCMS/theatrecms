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
    private int $id = 0;

    #[Column(name: 'first_name', type: 'string', unique: true, nullable: false)]
    private string $firstName;

    #[Column(name: 'last_name', type: 'string', unique: true, nullable: false)]
    private string $lastName;

    #[Column(type: 'text', nullable: true)]
    private string|null $biography;

    #[Column(name: 'headshot_url', type: 'string', nullable: true)]
    private string|null $headshotUrl;


    public function __construct(string $firstName, string $lastName, string $biography = null, string $headshotUrl = null)
    {
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->biography = $biography;
        $this->headshotUrl = $headshotUrl;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return implode(' ', [$this->getFirstName(), $this->getLastName()]);
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getBiography(): string
    {
        return $this->biography;
    }

    public function getHeadshotUrl(): string
    {
        return $this->headshotUrl ?? '';
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
