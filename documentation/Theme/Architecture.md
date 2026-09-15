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