<?php

namespace TheatreCMS\Auth;

use Delight\Auth\Auth;
use Delight\Auth\UnknownIdException;

readonly class AuthorizationService
{
    public function __construct(private Auth $auth, private CapabilityRegistry $registry)
    {
    }

    /**
     * Whether the currently signed-in user holds the given capability.
     */
    public function can(string $capability): bool
    {
        $roles = $this->registry->rolesFor($capability);

        if (empty($roles)) {
            return false;
        }

        return $this->auth->hasAnyRole(...$roles);
    }

    /**
     * Whether an arbitrary user (not necessarily the current session) holds the given capability.
     *
     * @throws UnknownIdException if no user with the given ID exists
     */
    public function userCan(int $userId, string $capability): bool
    {
        $roles = $this->registry->rolesFor($capability);

        if (empty($roles)) {
            return false;
        }

        foreach ($roles as $role) {
            if ($this->auth->admin()->doesUserHaveRole($userId, $role)) {
                return true;
            }
        }

        return false;
    }
}
