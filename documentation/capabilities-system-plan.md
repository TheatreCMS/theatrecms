# WordPress-Style Capabilities System for TheatreCMS

## Context

TheatreCMS currently has only a binary admin/not-admin distinction (`UserRepository::isAdmin()`), and authorization on every `/admin/*` route is just "are you logged in" (`AuthMiddleware`) — there is no per-action permission checking anywhere, and controllers/templates have no way to ask "can this user do X?". We need a real capabilities layer, similar to WordPress's `current_user_can('edit_posts')`, so that:

- Route groups and templates can check named capabilities instead of hardcoding role checks.
- Today, only two roles exist (`admin`, `user`), and `user` should have **no** admin-panel access at all.
- The system must be extensible later (more roles/capabilities) without a schema migration.

**Confirmed decisions** (from user):
1. Keep existing role slugs `'admin'` / `'user'` as-is (no rename to `'administrator'`).
2. The `user` role gets **zero** admin-panel capabilities for now — every current `/admin/*` route requires a capability only `admin` holds.

**Stack** (confirmed by reading the code): Slim Framework 4, Doctrine ORM 3 with attribute-mapped entities, Twig via `slim/twig-view`, PHP-DI container, `delight-im/auth` for authentication. The `users` table is managed entirely by `delight-im/auth` and is explicitly excluded from Doctrine's schema-diffing (`app/bootstrap.php` schema asset filter), so `User::$rolesMask` (`roles_mask` column) stays as the sole role-storage mechanism — no new migration.

The auth library already in use is not a passive bitmask store — its own docs (delight-im/php-auth README, "Permissions or access rights") explicitly recommend wrapping `hasRole()`/`hasAnyRole()` checks behind a centralized permission helper rather than scattering role checks through the codebase, and it reserves 23 named bitmask constants (`Role::ADMIN`, `Role::AUTHOR`, `Role::EDITOR`, `Role::CONTRIBUTOR`, `Role::MODERATOR`, ...) — far more than the single `ADMIN` bit currently used. This plan builds the capability layer directly on top of this mechanism (capability string → Delight `Role` constants) rather than inventing a parallel role-storage concept, so future roles need zero migrations — just assigning an already-reserved bit.

This follows the same WordPress-inspired registry pattern already established in this codebase for extensibility: `src/Theme/HookManager.php` and `src/Theme/MenuLocationRegistry.php` are singletons (`setInstance`/`getInstance`) populated at bootstrap time from small config files (`app/hooks.php`, `app/menu-locations.php`), registered in `src/DI/ServiceRegistrar.php`.

## Architecture

**Capability constants** — `src/Auth/Capability.php`, a plain final class of string constants (not an enum — WP capabilities are open-ended strings, and future roles/plugins should be able to introduce new ones without touching a closed enum):
```
MANAGE_USERS = 'manage_users'
MANAGE_OPTIONS = 'manage_options'      // site settings
MANAGE_MENUS = 'manage_menus'
UPLOAD_FILES = 'upload_files'          // image uploads
EDIT_POSTS = 'edit_posts'
EDIT_PAGES = 'edit_pages'
MANAGE_PRODUCTIONS = 'manage_productions'   // productions, seasons, events, venues, sponsors
MANAGE_PEOPLE = 'manage_people'             // people, works (creator/work catalog)
```
Content areas get a few grouped capabilities (not one per admin route, not one giant `is_admin`) — granular enough that a future `editor`-style role can be given `edit_posts` without `manage_users`, but not so granular it's 15 near-duplicate constants for a 2-role system.

**CapabilityRegistry** — `src/Auth/CapabilityRegistry.php`, a singleton mirroring `src/Theme/MenuLocationRegistry.php` exactly: `private static ?self $instance`, `setInstance()`/`getInstance()`, plus:
- `register(string $capability, array $roles): void` — `$roles` is a list of Delight `Role::*` int constants that grant this capability.
- `rolesFor(string $capability): array` — returns the registered role constants, or `[]` if unregistered (fail-closed).

Bootstrap file `app/capabilities.php` (same shape as `app/menu-locations.php`), `require_once`'d in `app/bootstrap.php` right after `menu-locations.php`:
```php
$capabilities->register(Capability::MANAGE_USERS, [Role::ADMIN]);
$capabilities->register(Capability::MANAGE_OPTIONS, [Role::ADMIN]);
$capabilities->register(Capability::MANAGE_MENUS, [Role::ADMIN]);
$capabilities->register(Capability::UPLOAD_FILES, [Role::ADMIN]);
$capabilities->register(Capability::EDIT_POSTS, [Role::ADMIN]);
$capabilities->register(Capability::EDIT_PAGES, [Role::ADMIN]);
$capabilities->register(Capability::MANAGE_PRODUCTIONS, [Role::ADMIN]);
$capabilities->register(Capability::MANAGE_PEOPLE, [Role::ADMIN]);
```
Every capability maps to `[Role::ADMIN]` today — `user` (0 bits set) is denied everything automatically, matching decision #2. Granting `user` (or a future role) partial access later is a one-line change here, no migration.

Register in `ServiceRegistrar::registerSharedServices()`: `$container->set(CapabilityRegistry::class, static fn(): CapabilityRegistry => new CapabilityRegistry());`, and set the static instance in `bootstrap.php` the same way `HookManager`/`MenuLocationRegistry` are set.

**AuthorizationService** — `src/Auth/AuthorizationService.php`, constructed with `Auth $auth` and `CapabilityRegistry $registry`:
- `can(string $capability): bool` → `!empty($this->registry->rolesFor($capability)) && $this->auth->hasAnyRole(...$this->registry->rolesFor($capability))`
- `userCan(int $userId, string $capability): bool` → for checking a non-current user (e.g. rendering a capability badge in the user list) — use `$this->auth->admin()->getRolesForUserById($userId)` intersected with `rolesFor()`, since `hasAnyRole()` only works for the logged-in session user.

Register in `ServiceRegistrar` as a normal factory: `new AuthorizationService($c->get(Auth::class), $c->get(CapabilityRegistry::class))`.

**RequireCapabilityMiddleware** — `src/Middleware/RequireCapabilityMiddleware.php`, implements `MiddlewareInterface`, constructor takes `AuthorizationService $authz` and `string $capability` (parametrized, so route files instantiate it directly rather than via `$container->get(Middleware::class)`):
```php
->add(new RequireCapabilityMiddleware($container->get(AuthorizationService::class), Capability::MANAGE_USERS))
```
On failure: return a plain `Response()->withStatus(403)` with a short text body — don't depend on Twig being configured yet, keep it consistent with `AuthMiddleware`'s simplicity. Ordering in each route group: `AuthMiddleware` (must be logged in) → `RequireCapabilityMiddleware` (must hold the capability) → `RequireTwigMiddleware`. Slim applies `->add()` in reverse order, so in code this is added as: `->add($container->get(RequireTwigMiddleware::class))->add(new RequireCapabilityMiddleware(...))->add($container->get(AuthMiddleware::class))`.

**Twig integration** — `src/Twig/CapabilityExtension.php`, mirrors `MenuExtension`: constructor-injected `AuthorizationService`, `getFunctions()` returns one `TwigFunction('can', [$this, 'can'])`. Registered in the `Twig::class` factory in `ServiceRegistrar` alongside `EditorJsExtension`/`MenuExtension`: `$twig->addExtension(new CapabilityExtension($c->get(AuthorizationService::class)));`. Enables `{% if can('manage_users') %}` in admin nav/sidebar templates to hide links the current user can't use.

## Rollout mapping (all require `Role::ADMIN` today via the capabilities above)

| Route group | Capability |
|---|---|
| `app/routes/admin/users.php` | `MANAGE_USERS` |
| `app/routes/admin/settings.php` | `MANAGE_OPTIONS` |
| `app/routes/admin/menus.php` | `MANAGE_MENUS` |
| `app/routes/admin/images.php` | `UPLOAD_FILES` |
| `app/routes/admin/posts.php` | `EDIT_POSTS` |
| `app/routes/admin/pages.php` | `EDIT_PAGES` |
| `app/routes/admin/productions.php`, `seasons.php`, `events.php`, `venues.php`, `sponsors.php` | `MANAGE_PRODUCTIONS` |
| `app/routes/admin/people.php`, `works.php` | `MANAGE_PEOPLE` |

Each file gets one added middleware line as shown above; no other changes to these route files.

## Tests

- `tests/Unit/Auth/CapabilityRegistryTest.php` (new): register/rolesFor/unregistered-returns-empty.
- `tests/Unit/Auth/AuthorizationServiceTest.php` (new): `can()` true/false against a fake `Auth`, `userCan()` for arbitrary user id.
- `tests/Unit/Middleware/TestRequireCapabilityMiddleware.php` (new): mirrors `tests/Unit/Middleware/TestAuthMiddleware.php` — 403 when capability denied, passthrough when granted.
- `tests/Unit/UsersControllerTest.php` / `tests/Unit/UserTest.php`: no changes expected — `resolveRoleLabel()`/`isAdmin()`/`syncRoleByUserId()` are untouched.

## Verification

1. `vendor/bin/phpunit` — new + existing suites pass.
2. Manually log in as a `user`-role account: confirm every `/admin/*` route now returns 403 (previously accessible to any logged-in user).
3. Log in as `admin`: confirm all admin routes behave exactly as before (no regression).
4. Confirm admin nav/sidebar template (wherever it lists section links) hides nothing for `admin` and, if a `user`-role test account is given the admin login page, that no admin nav links leak before the 403 (i.e. wrap nav links in `{% if can(...) %}` where applicable).

## Addendum: self-service profile management

Every authenticated user — `admin` or `user` — needs to manage their own account (email, username, password), independent of the capability system above. This is intentionally **not** modeled as a capability: Delight's `Role`-based `CapabilityRegistry` can only grant access to users holding a specific bitmask role, but `user` holds zero bits, so no capability could ever be "granted to everyone" without a wildcard mechanism the registry doesn't have. Editing your own record is instead treated as an authentication-only concern, matching WordPress's `profile.php` (which is reachable by any logged-in user regardless of role).

- **`src/Controllers/ProfileController.php`** — `edit`/`update`, always scoped to `$auth->getUserId()`; the client can never supply a target user ID. No role field exists anywhere in this flow, so self-service can't touch capabilities/role — that stays exclusively in `UsersController` (gated by `MANAGE_USERS`).
- Password changes go through `Auth::changePassword($oldPassword, $newPassword)` (requires the current password), not `admin()->changePasswordForUserById()` (which is the no-verification admin path) — self-service must prove account ownership before rotating its own password.
- **`app/routes/admin/profile.php`** — `/admin/profile` (GET/POST), gated by `AuthMiddleware` only, no `RequireCapabilityMiddleware` — this is the one admin-panel area intentionally open to both roles.
- `templates/admin/profile/edit.html.twig` mirrors `templates/admin/users/edit.html.twig` minus the role `<select>` and delete button, plus a "Current Password" field.
- `freshAlertResponse()` was promoted from a private `UsersController` method to `protected` on `BaseController` so `ProfileController` could reuse the same HTMX alert pattern (rule-of-two extraction, not a new abstraction).
- `templates/layouts/admin.html.twig`: added a "My Profile" link in the header (visible to everyone, since the route needs no capability), and retrofitted the sidebar `nav_items` loop to filter by `capability` (`null` = always visible) using the `can()` Twig function — now that `user` has a real, working admin-panel destination, the rest of the sidebar should stop advertising links that 403.
