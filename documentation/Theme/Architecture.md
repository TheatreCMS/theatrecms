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