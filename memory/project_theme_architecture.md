---
name: project-theme-architecture
description: TheatreCMS follows a WordPress-style theme model — default templates and styling live in a separate default theme package, not in core
metadata:
  type: project
---

Core (`templates/`) contains only admin-facing templates (login, admin UI, partials) and `layouts/admin.html.twig`. Front-end templates belong exclusively to themes.

**Why:** Keeping defaults in core created a hidden fallback that prevented the theme API from being complete. Separating them forces themes to be self-sufficient and lets themes version independently from core.

**How to apply:** New front-end templates (public-facing routes) always go into a theme, never into `templates/`. Admin/auth templates stay in `templates/`. When suggesting where to add new templates, always direct front-end ones to `www/themes/default/templates/` (or the active theme).

The default theme lives at `www/themes/default/` and is tracked in this repo (gitignore uses `/www/themes/*` + `!/www/themes/default` pattern). Other installed themes (e.g. `avlt`) are gitignored as external packages.

Menu location registrations (`primary`, `footer`) are registered in the theme's `functions.php`, not in `app/bootstrap.php`.
