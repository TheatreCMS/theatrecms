# External references

`external_references` records which TheatreCMS content an external record was imported or synced
into: WordPress post 1234 became production 7, Tessitura production season 5501 is also
production 7, Tessitura facility 12 is venue 3. Importers and syncs look a record up before
writing, so running them again updates the existing content instead of creating duplicates.

It is core infrastructure, not tied to any one source. The WordPress importer and the Tessitura
plugin both use it.

## Columns

| Column | Example | Notes |
|---|---|---|
| `source` | `wp`, `tessitura` | The external system. Short, lowercase, owned by the plugin that writes it |
| `source_type` | `post`, `attachment`, `production_season`, `facility` | The kind of record in that system |
| `source_id` | `1234` | The record's ID there, as a string |
| `content_type` | `production`, `person`, `venue` | The TheatreCMS type, using the same keys as term relationships and content meta |
| `content_id` | `7` | The content's ID. A soft reference: no foreign key |
| `synced_at` | | When the content was last imported or synced from the record |
| `created_at` | | When the link was first made |

`(source, source_type, source_id)` is unique: an external record points at one piece of content.
A piece of content can have several references, one per source or record (a production imported
from WordPress and linked to two Tessitura production seasons has three).

## Using it

`TheatreCMS\Repositories\ExternalReferenceRepository` (from the container):

```php
$reference = $references->find('wp', 'post', (string) $post->ID);

if ($reference === null) {
    $production = $productions->create([...]);
    $references->link('wp', 'post', (string) $post->ID, 'production', $production->getId());
} else {
    // update the existing production, then record the sync
    $references->touch('wp', 'post', (string) $post->ID);
}

$references->forContent('production', 7);   // every external record linked to production 7
```

- `link()` is an upsert: it creates the reference, or repoints an existing one, and sets `synced_at`.
- `touch()` only updates `synced_at`, and throws `InvalidArgumentException` if there is no reference.

## Deleting content

Deleting content does not delete its references; `content_id` has no foreign key, the same
approach as `content_meta` and term relationships. Two consequences:

1. **Code that deletes content which may have been imported or synced should also call
   `$references->deleteForContent($contentType, $contentId)`.** This is the cleanup hook. Core's
   admin delete actions don't call it yet.
2. **Importers and syncs must treat a reference whose content no longer exists as stale.** Load
   the content after `find()`; if it's gone, create it again and `link()` the same record to the
   new ID (which repoints the existing reference). Never skip a record just because a reference
   exists.

The table is created by `migrations/20261001_create_external_references_table.sql`.
