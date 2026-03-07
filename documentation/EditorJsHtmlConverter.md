# EditorJsHtmlConverter

A small, safe converter that turns Editor.js output into HTML suitable for rendering
in the Theatre CMS frontend.

File: `src/Text/EditorJsHtmlConverter.php`

Summary
- Accepts Editor.js payloads as a JSON string or a decoded PHP array (or a plain
  scalar/array which will be coerced to a single paragraph).
- Supports a number of common Editor.js block types and produces minimal, safe
  HTML fragments.
- Performs HTML sanitization and URL validation before returning content.

Quick usage

```php
use TheatreCMS\Text\EditorJsHtmlConverter;

$converter = new EditorJsHtmlConverter();

// Accepts a JSON string (Editor.js output) or decoded array
$html = $converter->toHtml($editorJsJsonString);

// Or, with a decoded array
$blocks = json_decode($editorJsJsonString, true);
$html = $converter->toHtml($blocks);
```

Supported block types
- paragraph: Default fallback block. Rendered as `<p>…</p>`.
- header: Rendered as `<h1>`–`<h6>` depending on the `level` field.
- list: Supports ordered and unordered lists; uses `<ol>` or `<ul>` with `<li>` items.
- quote: Rendered as a `<blockquote>` with optional `<cite>` for the caption.
- image: Renders an image inside a `<figure>` with `<img>` and optional `<figcaption>`.
  - Image URLs are validated; only `http` and `https` schemes are accepted.
  - Additional flags such as `stretched`, `withBorder`, and `withBackground` add CSS classes.
  - Images use `loading="lazy"` on the `<img>` tag.
- delimiter: Renders a simple `<hr />`.
- checklist: Renders an unordered list with a `data-checked` attribute per item.
- code: Rendered as `<pre><code>…</code></pre>` with the contents escaped.

Input shapes and normalization
- JSON string: The converter will attempt to decode the JSON. If decoding succeeds
  and a top-level `blocks` array is present, that array will be used. If decoding
  fails the raw string will be wrapped into a single paragraph block.
- Array: If the array contains a `blocks` key that is an array it will be used.
  If the array already looks like an Editor.js blocks array (every element has a `type` key)
  it will be used. Otherwise scalar elements are joined with newlines and coerced
  into a paragraph block.

Sanitization and safety
- The converter allows a very small set of inline tags to remain in text: `b`, `strong`,
  `i`, `em`, `u`, `mark`, `code`, `del`, `s`, and `br`.
- All other tags are stripped and attributes removed. The implementation then
  escapes the remaining text with `htmlspecialchars()` and re-inserts the allowed
  inline tags in a safe way.
- Image URLs are validated using `filter_var(..., FILTER_VALIDATE_URL)` and an
  explicit scheme check to allow only `http` and `https`.

Examples

1) Simple paragraph block (decoded array):

```php
$blocks = [
    [ 'type' => 'paragraph', 'data' => ['text' => 'Hello <b>world</b>!'] ],
];
$html = $converter->toHtml($blocks);
// => <p>Hello <b>world</b>!</p>
```

2) Header block

```php
$blocks = [
    [ 'type' => 'header', 'data' => ['level' => 2, 'text' => 'Section Title'] ],
];
// => <h2>Section Title</h2>
```

3) Image block (with a remote file URL)

```php
$blocks = [
    [
        'type' => 'image',
        'data' => [
            'file' => [ 'url' => 'https://example.org/image.jpg', 'caption' => 'Alt text' ],
            'stretched' => true,
        ],
    ],
];
$html = $converter->toHtml($blocks);
// => <figure class="editorjs-image editorjs-image--stretched"><img src="https://example.org/image.jpg" alt="Alt text" loading="lazy" /><figcaption>Alt text</figcaption></figure>
```

Security notes and compatibility
- The class was updated to be compatible with PHP 8+ (explicit scheme validation is used
  and a removed flag from older PHP versions is no longer used).
- The converter takes a conservative stance on allowed HTML and only permits a limited
  set of inline tags. If your application needs additional tags or attributes, consider
  either extending the converter carefully or using a robust HTML sanitizer library
  (for example: HTML Purifier) and reviewing XSS risks.

Where to find the source
- Source: `src/Text/EditorJsHtmlConverter.php`

Recommended next steps
- Add unit tests that exercise the block types and sanitization behavior. Suggested tests:
  - paragraph with allowed and disallowed tags
  - header levels 1..6
  - ordered/unordered lists with empty items
  - image block with invalid URL (should be skipped)
  - code block with special characters (must be escaped)
- If you need richer HTML sanitization rules, either extend `sanitizeText()` or replace
  it with a call to a well-tested library.

Contact / ownership
- The class lives under the `TheatreCMS\Text` namespace. If you want me to add
  unit tests or a small playground page in `www/` to visually inspect output, tell me
  which you'd prefer and I'll implement it.
