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
        editor.model.document.on('change:data', sync);
        sync();
        return editor;
    });
}
