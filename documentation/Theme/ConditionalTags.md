## Conditional tags

TheatreCMS's corollary to WordPress' `is_single()`/`is_archive()`/etc. family of conditional
tags — small boolean checks theme code and templates use to branch on what kind of page is
currently being rendered. Backed by `QueriedObject` (`src/Theme/QueriedObject.php`), which is
populated by `TemplateResolver::renderSingle()`/`renderList()` (the two methods every frontend
content route funnels through) right before the template renders.

### Available tags

| Function | True when |
|----------|-----------|
| `is_single()` | The current page is a single content-item view, of any type. |
| `is_item($itemSlug)` | The current page is the single view for the item with this slug. |
| `is_item_id($itemId)` | The current page is the single view for the item with this id. |
| `is_archive()` | The current page is an archive (listing) view, of any content type. |
| `is_archive($typeSlug)` | The current page is the archive for this specific content type. |
| `is_singular($typeSlug)` | The current page is the single view for this specific content type. |

`$typeSlug` matches the raw content-type string each route passes to `TemplateResolver` (e.g.
`'seasons'`, `'productions'`, `'people'`, `'works'`, `'pages'`) — not a URL segment. A
production is always type `'productions'` even though it's served nested under
`/seasons/{slug}/{productionSlug}`, so `is_singular('productions')` is true there regardless of
the URL shape.

### Usage

Like `add_filter()`/`apply_filters()`, these are available both as plain PHP functions (for
hook callbacks and other theme code) and as Twig functions:

```php
add_filter('theatrecms/schedule_details_footer', function (string $value) {
    if (is_singular('productions')) {
        $value .= '<p>Buy tickets for this production above.</p>';
    }

    return $value;
});
```

```twig
{% if is_single() %}
    {{ the_title(production) }}
{% elseif is_archive('productions') %}
    <h1>All productions</h1>
{% endif %}
```

### Implementation notes

Call these only from Twig templates, or from hook/action callbacks invoked *while* a template
is rendering (e.g. a filter fired by `the_content()` or `apply_filters()` from inside a
`.twig` file) — not from top-level code in a theme's `functions.php`. `functions.php` is
loaded once per request when the `Twig` service is first resolved, which always happens
*before* the matched route calls `renderSingle()`/`renderList()`; at that point no page has
been "queried" yet, so every conditional tag would report `false`.

Requests that never go through `TemplateResolver` (the home page, admin pages) correctly see
`is_single()`/`is_archive()` as `false` throughout.
