<?php

namespace Clubdeuce\Theaterpress\Models;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[Entity, Table(name: 'people')]
class Person
{
    #[Id, Column(type: 'integer'), GeneratedValue(strategy: 'AUTO')]
    private int $id;

    #[Column(type: 'string', unique: true, nullable: false)]
    private string $name;

    #[Column(type: 'text', nullable: true)]
    private string $biography;

    #[Column(type: 'string', nullable: true)]
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
}
