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
| `theatrecms/the_title` | Fired by the `the_title()` Twig function (see `src/Twig/TitleExtension.php`), TheatreCMS's corollary to WordPress' `the_title()`/filterable `the_title`. Resolves the display title for whatever content entity is passed to it (`Page`/`Post`/`Work` via `getTitle()`, `Production` via `getName()`, `Season` via `getLabel()`, `Person`/`Venue`/`Sponsor` via `getName()`), then lets themes filter the resolved string. | `string` (the resolved title) | The entity passed to `the_title()` |
| `theatrecms/the_content` | Fired by the `the_content()` Twig function (see `src/Twig/ContentExtension.php`), TheatreCMS's corollary to WordPress' `the_content()`/filterable `the_content`. Resolves the rendered HTML for whatever content entity is passed to it (`Page`/`Post` via `getContent()`, `Production`/`Work` via `getDescription()`, `Season` via `getOverview()` — each converted from Editor.js JSON — `Person` via `getBiography()`, already-sanitized HTML), then lets themes filter the resulting markup. | `string` (the resolved HTML) | The entity passed to `the_content()` |

Feel free to register new tags inside the theme or application code by calling `apply_filters()` manually wherever you need to expose a new extension point.

### Implementation notes

The `HookManager` persists callbacks by tag and priority, while `apply_filters()` loops through the registered callbacks in priority order. Theme authors can mutate the filtered value directly, and successive hooks will receive the mutated value.

Because the helpers live alongside the theme bootstrap, both theme developers and application code can rely on the same API: theme authors typically register filters from `themes/<active>/functions.php`, while application code calls `apply_filters()` to surface the new extension points before rendering Twig views.
