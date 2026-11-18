```
themes/
└── my-theme/
    ├── theme.json          ← metadata (name, version, author, etc.)
    ├── functions.php       ← theme hooks/filters, asset registration
    ├── assets/
    │   ├── css/
    │   └── js/
    └── templates/
        ├── layouts/
        │   ├── base.html.twig
        │   └── admin.html.twig  ← (admin stays in core, not themed)
        ├── index.html.twig
        └── partials/
            ├── header.html.twig
            ├── footer.html.twig
            └── nav.html.twig
```

See `hooks.md` for the `add_filter`/`apply_filters` API and `image-sizes.md`
for registering thumbnail sizes — both are things a theme's `functions.php`
typically does at load time.
## Linking assets

Wrap every `src`/`href` that points at a file under `www/` in `asset()`, so browsers fetch
the new copy after a deploy instead of a cached old one:

```twig
<link rel="stylesheet" href="{{ asset('/assets/css/menu.css') }}">
<script src="{{ asset('/themes/my-theme/assets/js/site.js') }}" defer></script>
```

`asset()` appends `?v=` plus the file's modification time; a path that isn't a local
file (a CDN URL, say) comes back unchanged. The admin layout's import map
(`asset_import_map()`) versions core's ES modules in `/assets/js/` the same way, so
`import ... from '/assets/js/editorjs-config.js'` needs no `asset()` call. See
`src/Twig/AssetExtension.php`.
