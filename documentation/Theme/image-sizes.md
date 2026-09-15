## Image Sizes

WordPress-style named thumbnail sizes for the media library. TheatreCMS's
core admin UI registers the sizes it needs to function; themes can register
additional sizes from `functions.php` the same way they register menu
locations (`register_menu_location()`, see `documentation/menu-system-plan.md`).
Every registered size — core or theme — is generated for every uploaded image,
so theme-registered sizes are available to public-facing templates as soon as
an image is uploaded, not just admin ones.

### Registering a size

```php
// in a theme's functions.php
register_image_size('hero', 1600, 900, true);   // hard crop, fills the box
register_image_size('card', 480, 320, false);   // scales to fit, no upscaling
```

`register_image_size(string $name, int $width, int $height, bool $crop = false)`
is defined in `app/image-sizes.php` and delegates to
`TheatreCMS\Theme\ImageSizeRegistry::register()`. `crop` controls how the size
is generated (see `src/Services/ImageVariantGenerator.php`):

- `crop = true` — hard-crops to exactly `width` x `height` (like CSS
  `object-cover`; always fills the box).
- `crop = false` (default) — scales proportionally to fit within
  `width` x `height`, without upscaling past the original's dimensions (like
  WordPress' default behavior for non-cropped sizes).

Core registers one size unconditionally, at bootstrap, before any theme code
runs: `admin-thumbnail` (300x300, cropped) — used by the media library grid
and picker (`templates/admin/media/_grid.html.twig`, `_picker.html.twig`).

### Generation

Sizes are generated eagerly, once, when an image is uploaded through the media
library (`MediaController::upload()`, via `ImageVariantGenerator::generate()`).
Each generated size is stored as a `MediaVariant` row (see
`src/Models/MediaVariant.php`) pointing at a sibling file in `/uploads/` named
`<original-basename>-<size-name>.<ext>`.

Images uploaded before a size was registered (or before this feature existed)
don't have variants until you run:

```
./regenerate-media-thumbnails [--dry-run] [--force] [--size=<name>]
```

By default this only generates sizes a given image doesn't already have.
`--force` regenerates every registered size for every image (e.g. after
changing a size's dimensions). `--size=<name>` restricts the run to one size
(useful right after registering a new one). See `src/Services/MediaVariantBackfillService.php`.

### Requesting a size

On a `Media` entity directly:

```twig
<img src="{{ media.getVariantUrl('admin-thumbnail') }}">
```

`getVariantUrl()` falls back to the original file's URL if the requested size
hasn't been generated for that image (e.g. it predates the size and
`regenerate-media-thumbnails` hasn't been run yet).

On any content entity with a featured image, via the existing
`the_featured_image_url()` Twig function (`src/Twig/FeaturedImageExtension.php`)
and its `theatrecms/the_featured_image_url` filter:

```twig
<img src="{{ the_featured_image_url(production, 'hero') }}">
```

The `$size` argument defaults to `'full'` (the original file), so existing
`the_featured_image_url(entity)` call sites are unaffected.
