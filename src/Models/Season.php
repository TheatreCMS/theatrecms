<?php

namespace Clubdeuce\Theaterpress\Models;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[Entity, Table(name: 'seasons')]
class Season implements \JsonSerializable
{
    #[Id, Column(type: 'integer'), GeneratedValue(strategy: 'AUTO')]
    private int $id;

    #[Column(type: 'string', nullable: false)]
    private string $slug;

    #[Column(type: 'string', nullable: false)]
    private string $label;

    public function __construct(string $slug, string $label)
    {
        $this->slug = $slug;
        $this->label = $label;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->getId(),
            'slug' => $this->getSlug(),
            'label' => $this->getLabel(),
        ];
    }

}