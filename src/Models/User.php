<?php

namespace Clubdeuce\TheatreCMS\Models;

use DateTimeImmutable;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[Entity, Table(name: 'users')]
final class User
{
    #[Id, Column(type: 'integer'), GeneratedValue(strategy: 'AUTO')]
    private int $id = 0;

    #[Column(type: 'string', unique: true, nullable: false)]
    private string $email;

    #[Column(name: 'registered_at', type: 'datetimetz_immutable', nullable: false)]
    private DateTimeImmutable $registeredDtm;

    public function __construct(string $email)
    {
        $this->email = $email;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setRegisteredDtm(DateTimeImmutable $registeredDtm): self
    {
        $this->registeredDtm = $registeredDtm;
        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getRegisteredDtm(): DateTimeImmutable
    {
        return $this->registeredDtm;
    }

    public function setEmail(string $string): self
    {
        $address = filter_var($string, FILTER_VALIDATE_EMAIL);

        if ($address)
            $this->email = $string;

        if (!$address)
            throw new \InvalidArgumentException('Invalid email address provided.');

        return $this;
    }
}
