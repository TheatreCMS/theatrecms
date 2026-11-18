// Behaviour for admin/partials/_edit_header.html.twig, the sticky header on admin
// create/edit pages. For the form it points at (data-form-id) it:
//   - tracks unsaved changes and shows them on the Save button and the status text,
//   - warns before leaving the page with unsaved changes,
//   - saves on Ctrl/Cmd+S,
//   - shows "Saving…" / "Saved" / "Couldn't save" from the htmx response,
//   - redirects to the list page after the overflow menu's Delete succeeds.
//
// Fields are compared against a snapshot taken on load and after each save, so undoing an
// edit makes the form clean again. Until the user first touches the form, the snapshot is
// retaken as scripts finish setting it up (pickers that render hidden inputs, editors that
// normalise their textarea), so that setup doesn't count as a change. Widgets that hold
// their state outside form fields (EditorJS, CKEditor) report changes by dispatching
// `edit-form:dirty` on the form, and a field they keep in sync can opt out of the snapshot
// with `data-edit-ignore`. A client-side save failure that never reaches the server is
// reported with `edit-form:error`.
//
// The project pins htmx 1.9.2 (no hx-on::<event> shorthand), so htmx events are handled
// with real listeners here.

const SAVED_MESSAGE_MS = 4000;

function snapshot(form) {
    const pairs = [];
    for (const el of form.elements) {
        if (!el.name || el.disabled || el.hasAttribute('data-edit-ignore')
            || ['button', 'submit', 'reset', 'image'].includes(el.type)) {
            continue;
        }
        if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) {
            continue;
        }
        if (el.type === 'file') {
            pairs.push([el.name, [...el.files].map((f) => `${f.name}:${f.size}`).join('|')]);
        } else if (el instanceof HTMLSelectElement && el.multiple) {
            pairs.push([el.name, [...el.selectedOptions].map((o) => o.value).join('|')]);
        } else {
            pairs.push([el.name, el.value]);
        }
    }
    return JSON.stringify(pairs);
}

// The htmx response is an alert partial (admin/partials/_alert.html.twig) swapped into the
// request's target, usually #flash-messages; its data-alert-type says whether the save worked.
function alertTypeIn(target) {
    const alert = target?.querySelector('[data-alert-type]')
        ?? document.querySelector('#flash-messages [data-alert-type]');
    return alert ? alert.dataset.alertType : null;
}

function initEditHeader(header) {
    const form = document.getElementById(header.dataset.formId);
    if (!form) {
        return;
    }

    const saveButton = header.querySelector('[data-edit-save]');
    const status = header.querySelector('[data-edit-status]');
    const menu = header.querySelector('[data-edit-menu]');
    const deleteButton = header.querySelector('[data-edit-delete]');
    const isCreate = header.hasAttribute('data-create');

    let baseline = snapshot(form);
    let externalDirty = false;
    let saving = false;
    let leaving = false;
    let statusTimer = null;
    let touched = false;
    // A page script may disable Save itself (e.g. until passwords match); restore that after a save.
    let disabledBeforeSave = false;

    const isDirty = () => externalDirty || snapshot(form) !== baseline;

    const setStatus = (text, tone = 'muted', clearAfter = 0) => {
        window.clearTimeout(statusTimer);
        status.textContent = text;
        status.className = 'text-sm ' + ({
            muted: 'text-gray-500',
            warn: 'text-yellow-700',
            error: 'text-red-700',
            ok: 'text-green-700',
        }[tone]);
        if (clearAfter) {
            statusTimer = window.setTimeout(() => {
                status.textContent = '';
                render();
            }, clearAfter);
        }
    };

    function render() {
        if (saving) {
            return;
        }
        const dirty = isDirty();
        saveButton.classList.toggle('is-clean', !dirty && !isCreate);
        if (dirty) {
            setStatus('Unsaved changes', 'warn');
        } else if (status.textContent === 'Unsaved changes') {
            // Only clear our own message; leave "Saved" / "Couldn't save" to time out.
            setStatus('');
        }
    }

    const markClean = () => {
        baseline = snapshot(form);
        externalDirty = false;
    };

    form.addEventListener('input', render);
    form.addEventListener('change', render);

    // Retake the baseline while page scripts finish initialising, unless the user has
    // already started editing.
    for (const type of ['keydown', 'pointerdown', 'input', 'change']) {
        form.addEventListener(type, () => { touched = true; }, { capture: true });
    }
    const rebaseline = () => {
        if (!touched && !saving) {
            baseline = snapshot(form);
            render();
        }
    };
    window.addEventListener('load', () => {
        rebaseline();
        window.setTimeout(rebaseline, 500);
        window.setTimeout(rebaseline, 1500);
    });

    // Picker widgets add and remove hidden inputs without firing input/change.
    let mutationTimer = null;
    new MutationObserver(() => {
        window.clearTimeout(mutationTimer);
        mutationTimer = window.setTimeout(render, 50);
    }).observe(form, { childList: true, subtree: true });
    form.addEventListener('edit-form:dirty', () => {
        externalDirty = true;
        render();
    });

    // Out-of-band swaps (e.g. picking a hero image) replace hidden inputs without firing
    // input/change, so re-check after any htmx swap.
    document.body.addEventListener('htmx:afterSettle', render);

    form.addEventListener('htmx:beforeRequest', (event) => {
        if (event.detail.elt !== form) {
            return;
        }
        saving = true;
        disabledBeforeSave = saveButton.disabled;
        saveButton.disabled = true;
        saveButton.setAttribute('aria-busy', 'true');
        setStatus('Saving…');
    });

    // A create form answers with HX-Redirect to the new item's edit page; mark the form
    // clean first so the leave-page warning doesn't block that navigation.
    form.addEventListener('htmx:beforeOnLoad', (event) => {
        if (event.detail.elt === form && event.detail.xhr.getResponseHeader('HX-Redirect')) {
            leaving = true;
        }
    });

    form.addEventListener('htmx:afterRequest', (event) => {
        if (event.detail.elt !== form) {
            return;
        }
        saving = false;
        saveButton.disabled = disabledBeforeSave;
        saveButton.removeAttribute('aria-busy');

        if (event.detail.successful && alertTypeIn(event.detail.target) === 'success') {
            markClean();
            render();
            setStatus('Saved', 'ok', SAVED_MESSAGE_MS);
            return;
        }

        render();
        setStatus('Couldn’t save', 'error');
        document.getElementById('flash-messages')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });

    form.addEventListener('edit-form:error', () => {
        saving = false;
        saveButton.disabled = disabledBeforeSave;
        saveButton.removeAttribute('aria-busy');
        render();
        setStatus('Couldn’t save', 'error');
        document.getElementById('flash-messages')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });

    // A form without hx-post submits as a normal page load; let it leave without a warning.
    // (Listening on window runs after the form's own submit handlers, so a handler that
    // cancels the submit, e.g. to save EditorJS first, is seen as defaultPrevented.)
    window.addEventListener('submit', (event) => {
        if (event.target === form && !event.defaultPrevented && !form.hasAttribute('hx-post')) {
            leaving = true;
        }
    });

    window.addEventListener('beforeunload', (event) => {
        if (!leaving && isDirty()) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && !event.altKey && event.key.toLowerCase() === 's') {
            event.preventDefault();
            if (!saving && !saveButton.disabled && saveButton.offsetParent !== null) {
                form.requestSubmit();
            }
        }
    });

    if (menu) {
        document.addEventListener('click', (event) => {
            if (menu.open && !menu.contains(event.target)) {
                menu.open = false;
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && menu.open) {
                menu.open = false;
                menu.querySelector('summary')?.focus();
            }
        });
    }

    if (deleteButton) {
        deleteButton.addEventListener('htmx:afterRequest', (event) => {
            if (event.detail.successful) {
                leaving = true;
                window.location.href = header.dataset.backUrl;
            } else {
                menu.open = false;
                setStatus('Couldn’t delete', 'error');
            }
        });
    }

    // Shadow under the header only once it is actually stuck to the top.
    const sentinel = document.createElement('div');
    header.before(sentinel);
    new IntersectionObserver(([entry]) => {
        header.classList.toggle('is-stuck', !entry.isIntersecting);
    }).observe(sentinel);

    render();
}

document.querySelectorAll('[data-edit-header]').forEach(initEditHeader);
