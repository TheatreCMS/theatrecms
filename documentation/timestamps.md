# Content timestamps

Every content model records when it was created and last modified, in `created_at` and
`modified_at` columns:

- Production, Person, Venue, Sponsor, Season, Work, Event, Media
- Post and Page (which also have `published_at`)
- Term, Menu, MenuItem and ContentMeta

The sitemap's `lastmod` and the WordPress importer's "edited since last sync" check rely on them.

## How they're kept current

Two traits in `src/Traits/` map the columns and update them through Doctrine lifecycle callbacks:

| Trait | Column | Set |
|---|---|---|
| `HasCreatedTimestamp` | `created_at` | on first persist, if the entity hasn't set it |
| `HasModifiedTimestamp` | `modified_at` | on first persist, if not set, and on every flush that changes the entity |

`HasTimestamps` adds `published_at` on top of `HasCreatedTimestamp`, for published content.

No controller or repository needs to set them. Flushing an entity with no changed fields leaves
`modified_at` alone. `touchModified()` is still there for recording a modification that doesn't
change a mapped field.

## Adding them to a model

Doctrine only runs a model's callbacks when the class is marked `#[HasLifecycleCallbacks]`, so a new
model (core's or a plugin's) needs both the traits and the attribute:

```php
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use TheatreCMS\Traits\HasCreatedTimestamp;
use TheatreCMS\Traits\HasModifiedTimestamp;

#[Entity, HasLifecycleCallbacks]
class Thing
{
    use HasCreatedTimestamp;
    use HasModifiedTimestamp;
}
```

And a migration adding the columns: `created_at DATETIME NOT NULL` and `modified_at DATETIME NOT NULL`
(see `migrations/20261005_add_content_timestamps.sql`, which also backfills existing rows).
