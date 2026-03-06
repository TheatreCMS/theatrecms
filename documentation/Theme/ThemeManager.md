## ThemeManager

`ThemeManager` is the entry point for loading and configuring front-end themes. It abstracts the filesystem layout under `themes/` and lets the application:

1. **Locate the active theme** by directory (default is `themes/default`).  
2. **Expose template directories** so Twig can prefer theme templates before falling back to the core views.  
3. **Read theme metadata** from `theme.json` (`name`, `description`, `version`, `author`, etc.).  
4. **Bootstrap any theme-specific PHP helpers** from `functions.php`, mirroring the `functions.php` pattern used in WordPress.

### Key public helpers

| Method | Purpose |
|--------|---------|
| `getThemeDir()` | Returns the filesystem path to the currently active theme. |
| `getTemplatesDir()` | Returns `themes/<active>/templates`, which is prepended on the Twig loader. |
| `getMetadata()` | Lazily reads `theme.json` and caches the decoded array for downstream components. |
| `loadFunctions()` | Includes `<theme>/functions.php` when it exists so theme authors can register hooks or expose helpers. |
| `configureTwig(Twig $twig, string $coreTemplatesDir)` | Prepends the theme templates path to the Twig loader, then registers the core templates under the `core` namespace as a fallback. |

### Typical usage

```php
$themeManager = new ThemeManager(__DIR__ . '/../themes', 'my-theme');
$themeManager->loadFunctions();
$themeManager->configureTwig($twig, __DIR__ . '/../templates');
```

When Twig renders a view, the loader will first check the themed template directory so theme overrides take precedence; if no themed file exists, developers should reference `core::templates/...` to fall back to the shared views.
