# Registering custom content types

Besides the built-in types described in this folder, plugins and themes can add their own
content types, such as press releases or education resources, without changing core or the
database schema.

> **Status:** registration, URL prefixes, config overrides and storage (`Entry`,
> `EntryRepository`) are in place. Admin screens and frontend routes for registered types are
> not built yet, so `public`, `supports` and `capability` are stored on the definition but
> nothing reads them so far.

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
| `supports` | `[]` | Standard entry fields it uses: `editor`, `excerpt`, `featured_image` |
| `capability` | `edit_posts` | Capability needed to manage its entries in the admin |

- **Names** are lowercase letters and underscores (`TheatreCMS\Routing\NamingRules`), with no
  length limit. Use the singular: `press_release`, not `press_releases`.
- **Unknown args** raise an `E_USER_NOTICE` each and are ignored; registration continues.
- **Registering a name twice throws** `InvalidArgumentException`, including a built-in name.

## Where to register

`ContentTypeRegistry` and `TaxonomyRegistry` start empty and only provide the mechanism.
Every type, core's included, is registered during bootstrap:

1. Core's built-in types (`season`, `production`, `person`, `work`, `post`) in
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
table keyed by `content_type`, so registering a type needs no migration. An entry has a title,
slug, EditorJS `content`, `excerpt`, status, featured image, and created/modified/published
timestamps.

`EntryRepository` scopes every query to one type. Bind the type with `forType()`, which returns
a copy of the repository:

```php
$releases = $container->get(EntryRepository::class)->forType('press_release');

$entry = $releases->create(['title' => 'Season Announced', 'status' => 'published']);
$releases->fetchBySlug('season-announced');
$releases->fetchPage(page: 1, perPage: 20, search: 'season', sort: 'publishedAt', direction: 'desc');
```

- Slugs are unique within a type, so two types can both have a `launch` entry.
- An unbound repository throws `LogicException` rather than reading every type's entries.
- `update()` and `delete()` reject an entry of another type; `delete()` also removes the
  entry's taxonomy term assignments.
- Search covers title and excerpt. Sort keys: `title`, `status`, `publishedAt`, `modifiedAt`.
  Public listings (`fetchPublicByIds()`) show published entries only.

## Templates and conditional tags

Content-type keys are singular everywhere code asks about a type: `is_singular('production')`,
`is_archive('season')` (see `documentation/Theme/ConditionalTags.md`). Theme template
directories for the built-ins are still plural (`productions/single.html.twig`); routes pass the
directory to `TemplateResolver::renderSingle()` / `renderList()` as `templateDir`.

## Taxonomies

A taxonomy is attached to content types by their singular names:
`register_taxonomy('topic', ['press_release'])`. See `documentation/Theme/taxonomies.md`.
