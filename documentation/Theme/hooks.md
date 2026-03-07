## Theme Hooks

The new hook system mirrors WordPress' `apply_filters` API and makes it possible to mutate PHP-side data before a Twig template is rendered. The `HookManager` service is registered inside the DI container and exposed globally through the `add_filter()`/`apply_filters()` helpers defined in `app/hooks.php`. Since those helpers are loaded before any theme `functions.php` file runs, theme authors can register filters the same way they would in WordPress.

### Registering filters

Theme authors just need to call `add_filter()` with the tag they want to target. Filters receive the value being filtered as the first argument, followed by any additional context provided at the call site:

```php
add_filter('theatrecms/season', function (Season $season, Request $request, array $routeArgs) {
    // mutate $season before Twig renders the season detail template
    return $season;
});
```

Priorities work like WordPress (lower values run earlier). You can register callbacks from a theme's `functions.php`, or from any PHP class that has access to the global helper.

### Available hooks

| Tag | Description | Filtered value | Additional context |
|-----|-------------|----------------|---------------------|
| `theatrecms/season` | Fired before rendering `templates/seasons/single*.twig`. | `Season` entity | `Request`, route arguments |
| `theatrecms/production` | Fired before rendering `templates/seasons/production.html.twig`. | `Production` entity | `Request`, route arguments |
| `theatrecms/seasons` | Fired before rendering `templates/seasons/list.html.twig`. | `array<Season>` | `Request` |

Feel free to register new tags inside the theme or application code by calling `apply_filters()` manually wherever you need to expose a new extension point.

### Implementation notes

The `HookManager` persists callbacks by tag and priority, while `apply_filters()` loops through the registered callbacks in priority order. Theme authors can mutate the filtered value directly, and successive hooks will receive the mutated value.

Because the helpers live alongside the theme bootstrap, both theme developers and application code can rely on the same API: theme authors typically register filters from `themes/<active>/functions.php`, while application code calls `apply_filters()` to surface the new extension points before rendering Twig views.
