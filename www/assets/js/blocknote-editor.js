/**
 * BlockNote editor integration for TheatreCMS admin.
 *
 * Usage: import { mountBlockNoteEditor } from '/assets/js/blocknote-editor.js';
 *
 * mountBlockNoteEditor({ holderId, fieldId, initialData, imageUploadEndpoint })
 *   holderId           - ID of the div to mount the editor into
 *   fieldId            - ID of the hidden textarea that stores JSON
 *   initialData        - Existing BlockNote JSON string (or null for new content)
 *   imageUploadEndpoint - POST endpoint that returns { success: 1, file: { url } }
 */

import React from 'react';
import { createRoot } from 'react-dom/client';
import { useCreateBlockNote, createReactBlockSpec, SuggestionMenuController, getDefaultReactSlashMenuItems } from '@blocknote/react';
import { BlockNoteView } from '@blocknote/mantine';
import { BlockNoteSchema, defaultBlockSpecs, filterSuggestionItems, insertOrUpdateBlock } from '@blocknote/core';

// ── Custom block: Callout ──────────────────────────────────────────────────
const calloutBlock = createReactBlockSpec(
    {
        type: 'callout',
        propSchema: {
            backgroundColor: { default: '#FFF8E7' },
            borderColor:     { default: '#F59E0B' },
            textColor:       { default: '#92400E' },
            icon:            { default: '💡' },
            label:           { default: '' },
        },
        content: 'inline',
    },
    {
        render: ({ block, contentRef }) => React.createElement(
            'div',
            {
                className: 'bn-callout',
                style: {
                    background:  block.props.backgroundColor,
                    borderLeft:  `4px solid ${block.props.borderColor}`,
                    color:       block.props.textColor,
                    padding:     '12px 16px',
                    borderRadius: '6px',
                    margin:      '4px 0',
                },
            },
            (block.props.icon || block.props.label)
                ? React.createElement(
                    'div',
                    { className: 'bn-callout__header', style: { display: 'flex', gap: '6px', marginBottom: '4px', fontWeight: 600 } },
                    block.props.icon  ? React.createElement('span', { className: 'bn-callout__icon'  }, block.props.icon)  : null,
                    block.props.label ? React.createElement('span', { className: 'bn-callout__label' }, block.props.label) : null,
                )
                : null,
            React.createElement('div', { className: 'bn-callout__body', ref: contentRef })
        ),
    }
);

// ── Custom block: Sponsor Block ────────────────────────────────────────────
const sponsorBlock = createReactBlockSpec(
    {
        type: 'sponsorBlock',
        propSchema: {
            name:       { default: '' },
            logoUrl:    { default: '' },
            websiteUrl: { default: '' },
        },
        content: 'none',
    },
    {
        render: ({ block }) => React.createElement(
            'div',
            { className: 'bn-sponsor-block', style: { border: '1px dashed #ccc', padding: '12px', borderRadius: '6px', textAlign: 'center', color: '#666' } },
            React.createElement('span', null, '🎭 Sponsor Block'),
            block.props.name ? React.createElement('span', { style: { marginLeft: '8px', fontWeight: 600 } }, block.props.name) : null,
        ),
    }
);

// ── Custom block: Schedule Block ───────────────────────────────────────────
const scheduleBlock = createReactBlockSpec(
    {
        type: 'scheduleBlock',
        propSchema: {},
        content: 'none',
    },
    {
        render: () => React.createElement(
            'div',
            { className: 'bn-schedule-block', style: { border: '1px dashed #ccc', padding: '12px', borderRadius: '6px', textAlign: 'center', color: '#666' } },
            React.createElement('span', null, '📅 Performance Schedule'),
        ),
    }
);

// ── Custom block: Image Gallery ────────────────────────────────────────────
const imageGalleryBlock = createReactBlockSpec(
    {
        type: 'imageGallery',
        propSchema: {
            layout:  { default: 'grid' },
            caption: { default: '' },
            items:   { default: '[]' },
        },
        content: 'none',
    },
    {
        render: ({ block }) => {
            let items = [];
            try { items = JSON.parse(block.props.items); } catch (e) { /* ignore */ }
            return React.createElement(
                'div',
                { className: 'bn-gallery-placeholder', style: { border: '1px dashed #ccc', padding: '12px', borderRadius: '6px' } },
                React.createElement('div', { style: { fontWeight: 600, marginBottom: '8px' } }, `🖼 Image Gallery (${items.length} image${items.length === 1 ? '' : 's'}, layout: ${block.props.layout})`),
                items.length > 0
                    ? React.createElement(
                        'div',
                        { style: { display: 'flex', flexWrap: 'wrap', gap: '8px' } },
                        items.map((item, idx) => React.createElement(
                            'img',
                            { key: idx, src: item.url, alt: item.caption || '', style: { width: '80px', height: '60px', objectFit: 'cover', borderRadius: '4px' } }
                        ))
                    )
                    : React.createElement('span', { style: { color: '#999' } }, 'No images yet'),
                block.props.caption ? React.createElement('p', { style: { marginTop: '8px', fontSize: '0.85em', color: '#666' } }, block.props.caption) : null,
            );
        },
    }
);

// ── Custom block: Columns (2-column layout) ───────────────────────────────
// Container block whose children are column blocks.
// Each column block can contain nested content blocks.
const columnsBlock = createReactBlockSpec(
    {
        type: 'columns',
        propSchema: {},
        content: 'none',
    },
    {
        render: ({ block, renderChildren }) => {
            const childCount = block.children ? block.children.length : 0;
            if (childCount === 0) {
                return React.createElement('div', { className: 'bn-columns bn-columns--empty', style: { border: '1px dashed #ccc', padding: '16px', borderRadius: '6px', textAlign: 'center', color: '#999' } }, '\u{1F4D0} Columns');
            }

            const flexBasis = 100 / childCount;
            return React.createElement(
                'div',
                { className: 'bn-columns', style: { display: 'flex', gap: '16px', alignItems: 'flex-start' } },
                block.children.map((childId, idx) =>
                    React.createElement(
                        'div',
                        {
                            key: childId,
                            className: 'bn-column',
                            style: { flex: `0 0 ${flexBasis}%`, minWidth: 0, borderRight: idx < childCount - 1 ? '1px solid #e5e7eb' : 'none', paddingRight: '8px' },
                        },
                        renderChildren(block)
                    )
                )
            );
        },
    }
);

// ── Custom block: Column (child of columns) ───────────────────────────────
const columnBlock = createReactBlockSpec(
    {
        type: 'column',
        propSchema: {},
        content: 'none',
    },
    {
        render: ({ renderChildren }) =>
            React.createElement('div', { className: 'bn-column__content' }, renderChildren()),
    }
);

// ── Schema ─────────────────────────────────────────────────────────────────
const schema = BlockNoteSchema.create({
    blockSpecs: {
        ...defaultBlockSpecs,
        callout:      calloutBlock,
        sponsorBlock: sponsorBlock,
        scheduleBlock: scheduleBlock,
        imageGallery:  imageGalleryBlock,
        column:        columnBlock,
        columns:       columnsBlock,
    },
});

// ── Suggestion menu items ─────────────────────────────────────────────────
const blockIcon = (svgChildren) =>
    React.createElement('svg', { width: 18, height: 18, viewBox: '0 0 18 18', fill: 'none', xmlns: 'http://www.w3.org/2000/svg' }, ...svgChildren);

const getCustomSlashMenuItems = (editor) => [
    ...getDefaultReactSlashMenuItems(editor),
    {
        title: 'Callout',
        onItemClick: () => insertOrUpdateBlock(editor, { type: 'callout', props: {} }),
        aliases: ['callout', 'note', 'tip', 'alert'],
        group: 'Custom',
        icon: blockIcon([
            React.createElement('rect', { x: 2, y: 2, width: 14, height: 14, rx: 2, stroke: 'currentColor', strokeWidth: 1.5 }),
            React.createElement('text', { x: 6, y: 13, fontSize: 10, fill: 'currentColor' }, 'i'),
        ]),
        subtext: 'A highlighted note block',
    },
    {
        title: 'Sponsor Block',
        onItemClick: () => insertOrUpdateBlock(editor, { type: 'sponsorBlock', props: {} }),
        aliases: ['sponsor', 'sponsors', 'partner'],
        group: 'Custom',
        icon: blockIcon([
            React.createElement('circle', { cx: 9, cy: 9, r: 7, stroke: 'currentColor', strokeWidth: 1.5 }),
            React.createElement('text', { x: 6, y: 13, fontSize: 9, fill: 'currentColor' }, 'S'),
        ]),
        subtext: 'Insert a sponsor placeholder',
    },
    {
        title: 'Schedule Block',
        onItemClick: () => insertOrUpdateBlock(editor, { type: 'scheduleBlock', props: {} }),
        aliases: ['schedule', 'performance', 'dates'],
        group: 'Custom',
        icon: blockIcon([
            React.createElement('rect', { x: 2, y: 3, width: 14, height: 13, rx: 2, stroke: 'currentColor', strokeWidth: 1.5 }),
            React.createElement('line', { x1: 2, y1: 7, x2: 16, y2: 7, stroke: 'currentColor', strokeWidth: 1.5 }),
        ]),
        subtext: 'Performance schedule placeholder',
    },
    {
        title: 'Image Gallery',
        onItemClick: () => insertOrUpdateBlock(editor, { type: 'imageGallery', props: {} }),
        aliases: ['gallery', 'images', 'photos'],
        group: 'Custom',
        icon: blockIcon([
            React.createElement('rect', { x: 2, y: 2, width: 14, height: 14, rx: 2, stroke: 'currentColor', strokeWidth: 1.5 }),
            React.createElement('circle', { cx: 6, cy: 6, r: 2, stroke: 'currentColor', strokeWidth: 1 }),
            React.createElement('polyline', { points: '2,14 6,10 9,13 12,9 16,13', stroke: 'currentColor', strokeWidth: 1 }),
        ]),
        subtext: 'Grid of images',
    },
    {
        title: 'Columns',
        onItemClick: () => insertOrUpdateBlock(editor, { type: 'columns', props: {} }),
        aliases: ['columns', 'layout', 'split'],
        group: 'Custom',
        icon: blockIcon([
            React.createElement('rect', { x: 1, y: 1, width: 7, height: 16, rx: 1, stroke: 'currentColor', strokeWidth: 1.5 }),
            React.createElement('rect', { x: 10, y: 1, width: 7, height: 16, rx: 1, stroke: 'currentColor', strokeWidth: 1.5 }),
        ]),
        subtext: 'Two-column layout',
    },
];

// ── React component ────────────────────────────────────────────────────────
function BlockNoteEditorComponent({ initialContent, onDocumentChange, uploadFile }) {
    const editor = useCreateBlockNote({
        schema,
        initialContent: initialContent || undefined,
        uploadFile,
    });

    React.useEffect(() => {
        return editor.onChange(() => {
            onDocumentChange(editor.document);
        });
    }, [editor, onDocumentChange]);

    return React.createElement(
        BlockNoteView,
        { editor, theme: 'light', slashMenu: false },
        React.createElement(SuggestionMenuController, {
            triggerCharacter: '/',
            getItems: async (query) => filterSuggestionItems(getCustomSlashMenuItems(editor), query),
        })
    );
}

// ── Public API ─────────────────────────────────────────────────────────────
export function mountBlockNoteEditor({ holderId, fieldId, initialData, imageUploadEndpoint }) {
    const holder = document.getElementById(holderId);
    const field  = document.getElementById(fieldId);

    if (!holder || !field) {
        console.error(`[BlockNote] Could not find #${holderId} or #${fieldId}`);
        return;
    }

    let initialContent = undefined;
    const raw = (initialData || '').trim();
    if (raw && raw !== '[]') {
        try {
            const parsed = JSON.parse(raw);
            if (Array.isArray(parsed) && parsed.length > 0) {
                initialContent = parsed;
            }
        } catch (e) {
            console.warn('[BlockNote] Could not parse initial content, starting empty.', e);
        }
    }

    const uploadFile = imageUploadEndpoint
        ? async (file) => {
            const formData = new FormData();
            formData.append('image', file);
            const resp = await fetch(imageUploadEndpoint, { method: 'POST', body: formData });
            const data = await resp.json();
            if (data.success === 1 && data.file?.url) {
                return data.file.url;
            }
            throw new Error('[BlockNote] Image upload failed');
        }
        : undefined;

    if (!field.value) {
        field.value = '[]';
    }

    const root = createRoot(holder);
    root.render(
        React.createElement(BlockNoteEditorComponent, {
            initialContent,
            onDocumentChange: (doc) => {
                field.value = JSON.stringify(doc);
            },
            uploadFile,
        })
    );
}
