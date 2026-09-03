// Wires up the "Choose Image…" featured-image picker modal shared by the
// Production/Post/Season/Venue create+edit forms. Mirrors production-quick-create.js's
// approach: the project pins htmx 1.9.2, which predates the hx-on::<event> shorthand,
// so the modal is opened/closed from a real htmx:afterRequest listener rather than an
// inline attribute.

// How long the "<file> selected." message stays visible before the modal auto-closes.
const SELECTION_CLOSE_DELAY_MS = 600;

function isSuccessfulSelection(target) {
    return !!target.querySelector('.text-green-700');
}

export function initFeaturedImagePicker() {
    document.body.addEventListener('htmx:afterRequest', function (event) {
        const target = event.detail && event.detail.target;
        if (!target) {
            return;
        }

        if (target.id === 'image-picker-modal-content') {
            const modal = document.getElementById('image-picker-modal');
            if (modal && typeof modal.showModal === 'function' && !modal.open) {
                modal.showModal();
            }
            return;
        }

        if (target.id === 'image-picker-result' && isSuccessfulSelection(target)) {
            window.setTimeout(function () {
                const modal = document.getElementById('image-picker-modal');
                if (modal && modal.open) {
                    modal.close();
                }
            }, SELECTION_CLOSE_DELAY_MS);
        }
    });
}
