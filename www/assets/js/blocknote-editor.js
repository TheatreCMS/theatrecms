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
import { useCreateBlockNote, BlockNoteView, createReactBlockSpec } from '@blocknote/react';
import { BlockNoteSchema, defaultBlockSpecs } from '@blocknote/core';

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

// ── Custom block: Column container ────────────────────────────────────────
const columnBlock = createReactBlockSpec(
    {
        type: 'columnBlock',
        propSchema: {},
        content: 'none',
    },
    {
        render: ({ contentRef }) => React.createElement(
            'div',
            { className: 'bn-column', style: { flex: 1, minWidth: 0 }, ref: contentRef }
        ),
    }
);

// ── Custom block: Columns layout ──────────────────────────────────────────
const columnsBlock = createReactBlockSpec(
    {
        type: 'columns',
        propSchema: {
            columnCount: { default: 2 },
        },
        content: 'none',
    },
    {
        render: ({ contentRef }) => React.createElement(
            'div',
            { className: 'bn-columns', style: { display: 'flex', gap: '16px', alignItems: 'flex-start' }, ref: contentRef }
        ),
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
        columnBlock:   columnBlock,
        columns:       columnsBlock,
    },
});

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

    return React.createElement(BlockNoteView, { editor, theme: 'light' });
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
