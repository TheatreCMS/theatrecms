import { ClassicEditor, Essentials, Bold, Italic, Paragraph, Font, Heading, Link } from 'ckeditor5';

const initEditors = () => {
    document.querySelectorAll('[data-editor]').forEach((field) => {
        if (!field) {
            return;
        }

        ClassicEditor.create(field, {
            licenseKey: 'GPL',
            plugins: [ Essentials, Bold, Italic, Paragraph, Font, Heading, Link ],
            toolbar: [
                'undo', 'redo', '|', 'heading', 'link', 'bold', 'italic', '|',
                'fontSize', 'fontFamily',
            ],
            link: {
                toolbar: [ 'linkPreview', '|', 'editLink', 'linkProperties', 'unlink' ]
            }
        }).catch((error) => console.error('CKEditor init failed', error));
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initEditors);
} else {
    initEditors();
}
