<?php

namespace TheatreCMS\Models;

final class User
{
    public function __construct(
        private readonly int $id,
        private readonly string $email,
        private readonly ?string $username,
        private readonly int $rolesMask,
        private readonly ?int $lastLogin
    ) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address provided.');
        }
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getUsername(): string
    {
        return $this->username ?? '';
    }

    public function getRolesMask(): int
    {
        return $this->rolesMask;
    }

    public function getLastLogin(): ?int
    {
        return $this->lastLogin;
    }
}
