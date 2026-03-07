<?php

namespace TheatreCMS\Models;

use DateTimeImmutable;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

final class User
{
    private int $id = 0;

    private string $email;

    private string $password;

    private string $username;

    private bool $status;

    private bool $verified;

    private bool $resettable;

    private int $rolesMask;

    private int $registered;

    private int $lastLogin;

    private int $forceLogout;

    public function __construct(string $email)
    {
        $this->email    = $email;
    }

    public function getId(): int
    {
        return $this->id;
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

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * Note: the property is misspelled as `$useername` (to match existing mapping).
     * We expose correctly-named accessors: getUsername / setUsername.
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $username = trim($username);

        if ($username === '') {
            throw new \InvalidArgumentException('Invalid username provided.');
        }

        $this->username = $username;
        return $this;
    }

    public function getStatus(): bool
    {
        return $this->status;
    }

    public function setStatus(bool $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function isVerified(): bool
    {
        return $this->verified;
    }

    public function setVerified(bool $verified): self
    {
        $this->verified = $verified;
        return $this;
    }

    public function isResettable(): bool
    {
        return $this->resettable;
    }

    public function setResettable(bool $resettable): self
    {
        $this->resettable = $resettable;
        return $this;
    }

    public function getRolesMask(): int
    {
        return $this->rolesMask;
    }

    public function setRolesMask(int $rolesMask): self
    {
        $this->rolesMask = $rolesMask;
        return $this;
    }

    public function getRegistered(): int
    {
        return $this->registered;
    }

    public function setRegistered(int $registered): self
    {
        $this->registered = $registered;
        return $this;
    }

    public function getLastLogin(): int
    {
        return $this->lastLogin ?? 0;
    }

    public function setLastLogin(int $lastLogin): self
    {
        $this->lastLogin = $lastLogin;
        return $this;
    }

    public function getForceLogout(): int
    {
        return $this->forceLogout;
    }

    public function setForceLogout(int $forceLogout): self
    {
        $this->forceLogout = $forceLogout;
        return $this;
    }
}
