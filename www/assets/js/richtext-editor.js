// Minimal rich-text editor for short admin fields (e.g. a person's
// biography) that only need basic inline formatting — unlike the full
// EditorJS block editor used for long-form content such as production
// descriptions, this is CKEditor5's ClassicEditor restricted to a small
// toolbar.
//
// The editor attaches to `holderId`, seeded from that element's existing
// content. A hidden <textarea name="..."> (`textareaId`) is kept in sync on
// every change so the form still submits the field normally — CKEditor5
// doesn't write back to a source element on its own.
import { ClassicEditor, Essentials, Paragraph, Bold, Italic } from 'ckeditor5';

export function initRichTextEditor(holderId, textareaId) {
    const holderEl = document.getElementById(holderId);
    const textarea = document.getElementById(textareaId);
    if (!holderEl || !textarea) {
        return Promise.resolve(null);
    }

    return ClassicEditor.create(holderEl, {
        licenseKey: 'GPL',
        plugins: [Essentials, Paragraph, Bold, Italic],
        toolbar: ['bold', 'italic'],
    }).then((editor) => {
        const sync = () => {
            textarea.value = editor.getData();
        };
        // CKEditor normalises the HTML on load, so the textarea is left out of the sticky
        // edit header's change tracking (edit-header.js) and edits are reported instead.
        textarea.setAttribute('data-edit-ignore', '');
        editor.model.document.on('change:data', () => {
            sync();
            textarea.closest('form')?.dispatchEvent(new Event('edit-form:dirty'));
        });
        sync();
        return editor;
    });
}
