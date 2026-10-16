## Taxonomy term archives

Every term of a registered taxonomy gets a public archive page listing the content items that
carry it, like WordPress's `/category/news`.

### Registering a taxonomy

`register_taxonomy(string $name, array $contentTypes, array $args = [])` (`app/taxonomies.php`).
Core's own taxonomies (`genre` for works, `post_category` for posts) are registered in
`app/core-taxonomies.php`; plugins register theirs in `register()` and themes in `functions.php`.

| Arg | Default | Meaning |
|-----|---------|---------|
| `label` | The name in title case | Plural display label |
| `singularLabel` | The name in title case | Singular display label |
| `multiple` | `true` | Whether an item may carry more than one term of this taxonomy |
| `capability` | `manage_options` | Capability needed to manage its terms in the admin |
| `urlPrefix` | The name with underscores as hyphens | URL path segment its term archives are served under |
| `hasArchive` | `true` | Whether its terms get public archive pages |

- **Names** are lowercase letters and underscores, with no length limit; registering a name
  twice throws.
- **`$contentTypes`** are singular content-type names (`'work'`, `'press_release'`); at least one
  is required.
- **Unknown args** raise an `E_USER_NOTICE` each and are ignored; registration continues.

### URLs

Term archives are served at `/{urlPrefix}/{term-slug}`. `urlPrefix` is a
`register_taxonomy()` argument that defaults to the taxonomy name with underscores turned
into hyphens:

```php
register_taxonomy('genre', ['work']);                                        // /genre/comedy
register_taxonomy('post_category', ['post'], ['urlPrefix' => 'category']);   // /category/news
register_taxonomy('audience', ['work'], ['hasArchive' => false]);            // no archive pages
```

A prefix must be lowercase letters and hyphens and can't be `admin`. Each prefix belongs to
exactly one taxonomy or content type (`UrlPrefixRegistry`): `register_taxonomy()` throws for a
prefix another taxonomy or a content type already uses, such as `works` or a rewritten `shows`.
That includes a taxonomy with `hasArchive: false`, which still claims its prefix.
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
