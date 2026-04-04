## TemplateResolver

When building theme-aware controllers, you often need to provide a list of candidate templates (for example `production-special.html.twig`, `production.html.twig`, `@core/production.html.twig`) and render the first one that exists. `TemplateResolver` encapsulates that logic.

### How it works

- Accepts a `Twig` instance plus any number of template names in descending order of specificity.
- Iterates over the candidates and asks the Twig loader whether each file exists.
- Returns the first existing template, or the last candidate if none match (ensuring there is always at least a fallback template to render).

### Example

```php
$resolver = new TemplateResolver();
$template = $resolver->resolve(
    $twig,
    "themes/season/special-season.html.twig",
    "themes/season/default-season.html.twig",
    "@core/season.html.twig"
);
return $twig->render($response, $template, $data);
```

This keeps controllers clean while still letting themes override any view without touching the core controller logic.
