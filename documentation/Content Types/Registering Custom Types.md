# Registering custom content types

Besides the built-in types described in this folder, plugins and themes can add their own
content types, such as press releases or education resources, without changing core or the
database schema.

A registered type gets admin screens (`/admin/{urlPrefix}`) and frontend routes from the
generic `EntryController`, and its items are stored as `Entry` rows. Core's `post` type works
exactly this way, so it's the reference for what a registered type can do.

## Registering a type

```php
register_content_type('press_release', [
    'label' => 'Press Releases',
    'singularLabel' => 'Press Release',
    'urlPrefix' => 'news',
]);
```

`register_content_type(string $name, array $args = [])` (`app/content-types.php`) returns the
type's `ContentTypeDefinition` (`src/ContentType/`).

| Arg | Default | Meaning |
|-----|---------|---------|
| `label` | `singularLabel` plus "s" (`Press Releases`); set it when that isn't the plural | Plural display label, used in page titles and menu links |
| `singularLabel` | The name in title case (`Press Release`) | Singular display label |
| `urlPrefix` | The name with underscores as hyphens (`press-release`) | URL path segment the type is served under |
| `hasArchive` | `true` | Whether the type gets a public listing page at `/{urlPrefix}` |
| `public` | `true` | Whether its entries get frontend routes at all |
| `supports` | `['editor']` | Standard fields it uses: `editor`, `excerpt`, `featured_image`, `hero_image` (see below) |
| `capability` | `manage_posts` | Capability needed to manage its entries in the admin |
| `hierarchical` | `false` | Whether an entry can have a parent entry of the same type (see below) |
| `menuIcon` | `null` (a generic icon) | [Font Awesome Free](https://fontawesome.com/search?ic=free) classes for its admin sidebar link, e.g. `fa-solid fa-bullhorn` |
| `ogType` | `website` | Open Graph `og:type` for its single pages; core's `post` uses `article` |

`supports` decides which fields the admin form shows and saves, and which image-removal routes
exist (`DELETE /admin/{urlPrefix}/{id}/featured-image` and `/hero-image`). Fields a type doesn't
support are ignored on save, so turning one off leaves stored values alone. The hero image is a
banner for the item's own single page, stored in `content_meta` as `hero_image_id`; core's
season, production, person, venue and post types support it.

- **Names** are lowercase letters and underscores (`TheatreCMS\Routing\NamingRules`), with no
  length limit. Use the singular: `press_release`, not `press_releases`.
- **Unknown args** raise an `E_USER_NOTICE` each and are ignored; registration continues.
- **Registering a name twice throws** `InvalidArgumentException`, including a built-in name.

## Where to register

`ContentTypeRegistry` and `TaxonomyRegistry` start empty and only provide the mechanism.
Every type, core's included, is registered during bootstrap:

1. Core's built-in types (`page`, `season`, `production`, `person`, `work`, `venue`, `event`,
   `sponsor`, `post`) in
   `app/core-content-types.php`, and core's taxonomies in `app/core-taxonomies.php`.
2. Plugins, in their `register()` method (see `documentation/plugins.md`).
3. The active theme, in its `functions.php`.

## URL prefixes

Each top-level URL prefix belongs to exactly one content type or taxonomy (`UrlPrefixRegistry`,
shared by both registries). A second claim on a prefix throws, whoever makes it, including a
taxonomy without term archives. A prefix must be lowercase letters and hyphens and can't be one
of `NamingRules::RESERVED_URL_PREFIXES` (`admin`).

Because the prefix defaults to the name, the built-ins set theirs explicitly so their URLs stay
plural (`season` is served under `/seasons`, `person` under `/people`). Do the same when your
type's URL should differ from its name.

One type may have an empty prefix and be served at the site root: core's `page`, at `/about`.
It can't have an archive (that would be the home page), so it must be registered with
`hasArchive: false`, and its routes are registered after every other route, plugins' included,
so its catch-all can't shadow them. Its admin screens and template directory use its label
instead (`/admin/pages`, `pages/single.html.twig`).

## Permalinks and hierarchy

`PermalinkResolver` (`the_permalink()`) builds every content URL from the item's type
definition, through the `HasContentType` interface its model implements: `/{urlPrefix}/{slug}`.
A type that isn't `public` has no URL; resolving one throws.

A `hierarchical` type's items can sit under a parent item of the same type, and their URL is the
parent's plus their own slug: `/programs/education/camps`, or for pages `/about/board-of-directors`.
Entries support this through the `HasParent` trait (`parent_id`), and the type's single route
matches the whole path (`/{urlPrefix}/{path:.+}`). An entry is found by walking the path from a
top-level entry, so an unpublished ancestor hides its descendants. Deleting an entry moves its
children up to its own parent. Entry slugs stay unique per type, so moving an entry never
renames it.

Productions are the one item whose parent is of another type: a production implements
`IsHierarchical` with its season as parent, so its URL is `/seasons/{season}/{production}`,
and its slug only has to be unique within its season. `ProductionController::hasSingleRoute()`
returns false, so `/productions/{slug}`, which couldn't tell two seasons' productions apart,
isn't routed; `/productions` stays the archive.

## Site overrides

A site can change any registered type's settings in `app/config.yaml`, keyed by the type's
singular name. The override accepts the same args as `register_content_type()` and wins over
them:

```yaml
content_types:
    season:
        urlPrefix: shows
        label: Shows
    press_release:
        hasArchive: false
```

An override is applied when its type registers, so it works for plugin types too. An override
that isn't a map, or has an unknown key, throws when the registry is built; an invalid or taken
prefix throws when the type registers. An override for a name nothing registers is ignored.

## Looking types up

`ContentTypeRegistry` (from the container, or `ContentTypeRegistry::getInstance()`):

| Method | Returns |
|--------|---------|
| `has($name)` / `get($name)` | Whether it's registered / its definition or `null` |
| `require($name)` | Its definition; throws for an unregistered name |
| `all()` | Every definition, keyed by name |
| `prefix($name)`, `label($name)`, `hasArchive($name)` | One setting; throw for an unregistered name |

There are no plural aliases: `prefix('seasons')` throws. `PermalinkResolver::archiveUrl($name)`
and the `archive_url()` Twig function build a type's archive URL from its prefix.

## Storing entries

Items of registered types are `Entry` models (`src/Models/Entry.php`), all in one `entries`
table keyed by `content_type`, so registering a type needs no migration. Posts are entries too
(`content_type = 'post'`). An entry has a title, slug, EditorJS `content`, `excerpt`, status,
featured image, hero image, and created/modified/published timestamps.

`EntryRepository` scopes every query to one type. Bind the type with `forType()`, which returns
a copy of the repository:

```php
$releases = $container->get(EntryRepository::class)->forType('press_release');

$entry = $releases->create(['title' => 'Season Announced', 'status' => 'published']);
$releases->fetchBySlug('season-announced');
$releases->fetchPage(page: 1, perPage: 20, search: 'season', sort: 'publishedAt', direction: 'desc');
```

- Slugs are unique within a type, so two types can both have a `launch` entry. On MariaDB,
  `create()` holds an advisory lock per type while it picks a slug; if one still can't be
  allocated, it throws `SlugAllocationException`, which the admin reports as a 409.
- Single-entry fetches (`fetch()`, `fetchBySlug()`, `fetchPublicBySlug()`, `fetchPublicByPath()`)
  load the hero image; listings don't.
- An unbound repository throws `LogicException` rather than reading every type's entries.
- `update()` and `delete()` reject an entry of another type; `delete()` also removes the
  entry's taxonomy term assignments.
- Search covers title and excerpt. Sort keys: `title`, `status`, `publishedAt`, `modifiedAt`.
  Public listings (`fetchPublicByIds()`) show published entries only.

## Templates and conditional tags

Content-type keys are singular everywhere code asks about a type: `is_singular('production')`,
`is_archive('season')` (see `documentation/Theme/ConditionalTags.md`). Theme template
directories are named after the URL prefix, so the built-ins' are plural
(`productions/single.html.twig`), and the root type's after its label (`pages/`);
`ContentTypeDefinition::templateDir()` gives the directory routes pass to
`TemplateResolver::renderSingle()` / `renderArchive()` as `templateDir`.

## Taxonomies

A taxonomy is attached to content types by their singular names:
`register_taxonomy('topic', ['press_release'])`. See `documentation/Theme/taxonomies.md`.
