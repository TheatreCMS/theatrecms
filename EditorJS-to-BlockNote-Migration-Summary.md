# EditorJS → BlockNote Migration Summary

**Date:** 2026-05-03  
**Branch:** HEAD (main)

---

## Overview

Replaced EditorJS with BlockNote.js as the rich-text block editor across all admin forms in TheatreCMS. The migration touched the PHP rendering layer, a new data migration script, a new shared JS editor module, the admin layout, and all 8 admin form templates.

---

## Files Created

### PHP Backend

| File | Purpose |
|---|---|
| `src/Text/BlockNoteHtmlConverter.php` | Converts BlockNote JSON array to safe HTML for frontend rendering |
| `src/Twig/BlockNoteExtension.php` | Registers the `block_content_to_html` Twig filter |
| `src/Text/EditorJsToBlockNoteConverter.php` | One-time migration converter (EditorJS JSON → BlockNote JSON) |
| `migrations/migrate_editorjs_to_blocknote.php` | Standalone CLI script to migrate database content |
| `tests/Unit/BlockNoteConverterTest.php` | 20 unit tests for BlockNoteHtmlConverter (all passing) |

### Frontend

| File | Purpose |
|---|---|
| `www/assets/js/blocknote-editor.js` | Shared ES module: mounts BlockNote editor into any admin form |
| `documentation/BlockNoteHtmlConverter.md` | Updated documentation for the new converter |

---

## Files Modified

### PHP / DI

- **`src/DI/ServiceRegistrar.php`** — swapped `EditorJsHtmlConverter` / `EditorJsExtension` imports and registration for BlockNote equivalents

### Twig Templates — Frontend Render

- **`templates/posts/_post.html.twig`** — filter renamed: `editorjs_to_html` → `block_content_to_html`
- **`templates/seasons/production.html.twig`** — same filter rename

### Twig Templates — Admin Layout

- **`templates/layouts/admin.html.twig`** — removed 15 EditorJS `<script>` tags; added BlockNote CSS links; extended importmap with React + BlockNote CDN entries

### Twig Templates — Admin Forms (all 8)

| Template | fieldId | imageUploadEndpoint |
|---|---|---|
| `templates/admin/posts/create.html.twig` | `content` | `/admin/images/upload` |
| `templates/admin/posts/edit.html.twig` | `content` | `/admin/images/upload` |
| `templates/admin/pages/create.html.twig` | `content` | `/admin/pages/images/upload` |
| `templates/admin/pages/edit.html.twig` | `content` | `/admin/pages/images/upload` |
| `templates/admin/productions/create.html.twig` | `description` | `/admin/productions/images/upload` |
| `templates/admin/productions/edit.html.twig` | `description` | `/admin/productions/images/upload` |
| `templates/admin/works/create.html.twig` | `description` | *(none)* |
| `templates/admin/works/edit.html.twig` | `description` | *(none)* |

Each template change:
1. Replaced `<div id="editorjs">` / `<div id="editorjs-description">` with `<div id="blocknote-holder" class="... min-h-[200px]">`
2. Replaced the entire `{% block scripts %}` EditorJS init (async `editor.save()` pattern) with a `mountBlockNoteEditor(...)` call and simplified synchronous submit handler

### package.json

Removed all EditorJS devDependencies:
- `@calumk/editorjs-columns`
- `@editorjs/editorjs`
- `@editorjs/header`
- `@editorjs/image`
- `@editorjs/link`
- `@editorjs/list`
- `@editorjs/quote`
- `@editorjs/table`
- `@editorjs/underline`
- `@editorjs/text-variant-tune`

---

## Files Deleted

| File | Reason |
|---|---|
| `www/assets/js/editorjs/` (15 files) | EditorJS plugin scripts no longer needed |
| `www/assets/js/editorjs-config.js` | EditorJS configuration factory |
| `src/Text/EditorJsHtmlConverter.php` | Replaced by BlockNoteHtmlConverter |
| `src/Twig/EditorJsExtension.php` | Replaced by BlockNoteExtension |
| `tests/Unit/EditorJsFilterTest.php` | Replaced by BlockNoteConverterTest |
| `documentation/EditorJsHtmlConverter.md` | Replaced by BlockNoteHtmlConverter.md |

---

## Key Architecture Decisions

- **React via CDN** (esm.sh importmap, no build step) — consistent with how CKEditor5 is loaded
- **All 5 custom blocks re-implemented** in BlockNote: `callout`, `sponsorBlock`, `scheduleBlock`, `imageGallery`, `columns` / `columnBlock`
- **No async save on submit** — BlockNote's `onChange` callback keeps the hidden textarea in sync at all times; submit just dispatches `htmxSubmit` immediately
- **imageGallery items** stored as a JSON-encoded string in `props.items` (BlockNote custom block props only support primitives)
- **List grouping** — BlockNote stores one block per list item; the PHP converter groups consecutive `bulletListItem` / `numberedListItem` blocks into a single `<ul>` / `<ol>`
- **Legacy data safety** — `BlockNoteHtmlConverter::extractBlocks()` returns `[]` gracefully if given EditorJS JSON (starts with `{`); migration script skips rows already starting with `[`

---

## Data Migration

To migrate existing database content from EditorJS JSON to BlockNote JSON, run inside DDEV:

```bash
php migrations/migrate_editorjs_to_blocknote.php
```

Targets: `posts.content`, `pages.content`, `productions.description`, `works.description`.  
Rows already starting with `[` are skipped (already BlockNote format).  
Each table runs in its own transaction with rollback on error.

---

## Verification

```bash
# PHP unit tests (all 20 pass)
./vendor/bin/phpunit tests/Unit/BlockNoteConverterTest.php

# Confirm no EditorJS references remain
grep -r "editorjs_to_html\|EditorJsHtmlConverter\|EditorJsExtension\|buildEditorJsConfig\|editorjs-config\|editorjs/" \
  . --include="*.php" --include="*.twig" --include="*.js" \
  --exclude-dir=vendor --exclude-dir=node_modules
# Expected: zero results
```
