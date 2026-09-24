## Taxonomy term archives

Every term of a registered taxonomy (see `register_taxonomy()` in `app/taxonomies.php`) gets
a public archive page listing the content items that carry it, like WordPress's
`/category/news`.

### URLs

Term archives are served at `/{url_prefix}/{term-slug}`. `url_prefix` is a
`register_taxonomy()` argument that defaults to the taxonomy name with underscores turned
into hyphens:

```php
register_taxonomy('genre', ['work']);                                        // /genre/comedy
register_taxonomy('post_category', ['post'], ['url_prefix' => 'category']);  // /category/news
register_taxonomy('audience', ['work'], ['has_archive' => false]);           // no archive pages
```

A prefix must be lowercase letters, digits or hyphens. It can't be `admin` and can't be
shared with another taxonomy (`register_taxonomy()` throws), and it can't match a content
type's URL prefix such as `works` or a rewritten `shows` (route registration throws).
`the_permalink(term)` builds a term's archive URL.

### What's listed

For each content type the taxonomy applies to, the archive lists the items carrying the term
that the public may see, in that content type's normal list order. Draft posts are left out.
Content types without a mapped repository in `TermArchiveQuery` (see `ServiceRegistrar`) are
skipped. Archives aren't paginated.

The `theatrecms/term` and `theatrecms/term_archive_items` filters (see `hooks.md`) can adjust
the term and the listed items before rendering.

### Template hierarchy

`TemplateResolver::renderTerm()` renders the first of these templates that exists:

1. `taxonomy/{taxonomy}-{term-slug}.html.twig`, e.g. `taxonomy/genre-comedy.html.twig`
2. `taxonomy/{taxonomy}.html.twig`
3. `taxonomy.html.twig`
4. `list.html.twig`
5. `index.html.twig`

Template context:

| Key | Value |
|-----|-------|
| `term` | The `Term` (`name`, `slug`, `description`) |
| `taxonomy` | Its `TaxonomyDefinition` (`name`, `label`, `singularLabel`, ...) |
| `items_by_type` | Singular content type => items, e.g. `{'work': [...]}` |
| `posts` | Every item, flattened across content types, for generic list/index templates |
| `page` | `{title: term name}` |
| `seo` | `SeoMeta` for the term (title, description from the term's description, canonical URL) |

Use `is_tax()` (see `ConditionalTags.md`) to branch on term archives in shared templates.
