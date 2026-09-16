// Wires up the media library's "details" slide-over dialog, opened by clicking
// a tile in the full-library grid. Mirrors featured-image-picker.js's approach:
// the project pins htmx 1.9.2, which predates the hx-on::<event> shorthand, so
// the modal is opened/closed from a real htmx:afterRequest listener rather than
// an inline attribute.

export function initMediaDetailsModal() {
    document.body.addEventListener('htmx:afterRequest', function (event) {
        const target = event.detail && event.detail.target;
        if (!target) {
            return;
        }

        if (target.id === 'media-details-modal-content') {
            const modal = document.getElementById('media-details-modal');
            if (modal && typeof modal.showModal === 'function' && !modal.open) {
                modal.showModal();
            }
            return;
        }

        // A successful delete re-renders #media-grid (the modal's hx-delete
        // target); the details dialog's content is now stale, so close it.
        if (target.id === 'media-grid') {
            const modal = document.getElementById('media-details-modal');
            if (modal && modal.open) {
                modal.close();
            }
        }
    });
}
