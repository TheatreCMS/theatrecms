# BlockNoteHtmlConverter

A safe converter that turns BlockNote.js JSON output into HTML suitable for rendering in the TheatreCMS frontend.

File: `src/Text/BlockNoteHtmlConverter.php`

## Summary

- Accepts a BlockNote JSON array (bare array of block objects) as a JSON string or a decoded PHP array.
- Supports all standard BlockNote block types plus the five custom blocks used in TheatreCMS.
- Groups consecutive list items (`bulletListItem`, `numberedListItem`, `checkListItem`) into a single `<ul>` or `<ol>` wrapper.
- Performs HTML sanitization and URL validation before returning content.
- Gracefully returns `''` when passed legacy EditorJS JSON (starts with `{`) or empty/null input.

## Quick usage

```php
use TheatreCMS\Text\BlockNoteHtmlConverter;

$converter = new BlockNoteHtmlConverter();

// Accepts a BlockNote JSON string or decoded array
$html = $converter->toHtml($blockNoteJsonString);

// Or, with a decoded array
$blocks = json_decode($blockNoteJsonString, true);
$html = $converter->toHtml($blocks);
```

## Supported block types

### Standard BlockNote blocks
- `paragraph`: Rendered as `<p>…</p>`, respects `textAlignment` prop.
- `heading`: Rendered as `<h1>`–`<h3>` based on `props.level`.
- `bulletListItem`: Grouped with adjacent items into `<ul><li>…</li></ul>`.
- `numberedListItem`: Grouped with adjacent items into `<ol><li>…</li></ol>`.
- `checkListItem`: Grouped into `<ul class="bn-checklist"><li>…</li></ul>`. Checked items get `data-checked="true"`.
- `quote`: Rendered as `<blockquote>…</blockquote>`.
- `image`: Renders inside `<figure>` with optional `<figcaption>`. URL must be `http` or `https`.
- `codeBlock`: Rendered as `<pre><code>…</code></pre>` with escaped content.
- `table`: Rendered as `<table>` from `content.rows[].cells[]`.
- `divider`: Renders `<hr />`.

### Custom TheatreCMS blocks
- `callout`: Styled `<div class="bn-callout">` with configurable background, border, text colors, icon, and label from props.
- `imageGallery`: `<div class="bn-image-gallery">` with items parsed from `props.items` (a JSON-encoded string).
- `sponsorBlock`: `<div class="bn-sponsor-block">` with name, logo image, and website link from props.
- `scheduleBlock`: `<div class="bn-schedule-block">` placeholder.
- `columns`: `<div class="bn-columns">` wrapping `<div class="bn-column">` for each `columnBlock` child, rendered recursively.

## Inline content

BlockNote inline content is a structured array of typed objects, not raw HTML. The converter handles:
- `type: "text"` — applies `bold` → `<strong>`, `italic` → `<em>`, `underline` → `<u>`, `strikethrough` → `<s>`, `code` → `<code>` around escaped text.
- `type: "link"` — validates `href` (http/https only) and emits `<a href="…">…</a>` with recursively-rendered content.

## Sanitization and safety

- All text values pass through `htmlspecialchars()`.
- Image and link URLs are validated via `filter_var(..., FILTER_VALIDATE_URL)` with an explicit `http`/`https` scheme check.
- A small set of inline tags (`b`, `strong`, `i`, `em`, `u`, `code`, `s`, `del`, `br`, `mark`) is allowed through `sanitizeText()` for any raw HTML fallback paths.

## Examples

1) Paragraph with bold inline content:

```php
$blocks = [[
    'type' => 'paragraph',
    'props' => ['textAlignment' => 'left'],
    'content' => [['type' => 'text', 'text' => 'Hello ', 'styles' => ['bold' => true]], ['type' => 'text', 'text' => 'world', 'styles' => []]],
    'children' => [],
]];
$html = $converter->toHtml($blocks);
// => <p><strong>Hello </strong>world</p>
```

2) Bullet list (two items auto-grouped):

```php
$blocks = [
    ['type' => 'bulletListItem', 'content' => [['type' => 'text', 'text' => 'First', 'styles' => []]], 'children' => []],
    ['type' => 'bulletListItem', 'content' => [['type' => 'text', 'text' => 'Second', 'styles' => []]], 'children' => []],
];
$html = $converter->toHtml($blocks);
// => <ul><li>First</li><li>Second</li></ul>
```

3) Heading:

```php
$blocks = [['type' => 'heading', 'props' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Section', 'styles' => []]], 'children' => []]];
$html = $converter->toHtml($blocks);
// => <h2>Section</h2>
```

## Twig filter

Registered as `block_content_to_html` via `BlockNoteExtension`:

```twig
{{ post.content | block_content_to_html }}
```

## Where to find the source

- Converter: `src/Text/BlockNoteHtmlConverter.php`
- Twig extension: `src/Twig/BlockNoteExtension.php`
- Tests: `tests/Unit/BlockNoteConverterTest.php`
