<?php

namespace TheatreCMS\Controllers;

/**
 * A content type's controller with admin actions beyond the standard CRUD routes `RouteRegistry`
 * gives every type (e.g. a quick-create modal). The admin-side counterpart of
 * `PublicContentController::additionalPublicRoutes()`.
 */
interface HasAdditionalAdminRoutes
{
    /**
     * Extra admin routes under `/admin/{type}`, as `[method, path, action]` (e.g.
     * `['get', '/quick-create', 'quickCreate']`). They're registered after the standard static
     * paths (`/create`, `/edit`) and share the type's capability check.
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    public static function additionalAdminRoutes(): array;
}
