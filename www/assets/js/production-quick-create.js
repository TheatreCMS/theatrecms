// Wires up the "+ New …" quick-create modal buttons on the production create/edit pages.
// When a controller's quickStore() action succeeds it responds with an HX-Trigger:
// entityCreated header carrying { id, name, type }. We listen for that and drop the
// freshly created record into the relevant <select> on the page instead of forcing a
// full page reload, so the user never has to leave the production form.

function buildOption(id, name) {
    const option = document.createElement('option');
    option.value = id;
    option.textContent = name;
    return option;
}

// searchable-select.js wraps an enhanced <select> in a `.space-y-1` div alongside the
// visible text <input> and its <datalist>. Appending an <option> to the (now hidden)
// select alone does not update what the user sees, so both must be kept in sync here.
function searchableWrapperFor(select) {
    return select.closest('.space-y-1');
}

function addDatalistEntry(select, id, name) {
    const wrapper = searchableWrapperFor(select);
    const datalist = wrapper ? wrapper.querySelector('datalist') : null;
    if (!datalist) {
        return;
    }
    const entry = document.createElement('option');
    entry.value = name;
    entry.dataset.optionValue = String(id);
    datalist.appendChild(entry);
}

function selectValue(select, id, name) {
    select.value = String(id);
    const wrapper = searchableWrapperFor(select);
    const input = wrapper ? wrapper.querySelector('input[type="text"]') : null;
    if (input) {
        input.value = name;
    }
}

function appendOptionTo(select, id, name) {
    select.appendChild(buildOption(id, name));
    addDatalistEntry(select, id, name);
}

function populateSingleSelect(selectName, id, name, buildFallback) {
    const select = document.querySelector(`select[name="${selectName}"]`);
    if (select) {
        appendOptionTo(select, id, name);
        selectValue(select, id, name);
        return;
    }
    if (typeof buildFallback === 'function') {
        buildFallback(id, name);
    }
}

function populateMultiSelect(elementId, id, name) {
    const select = document.getElementById(elementId);
    if (!select) {
        return;
    }
    const option = buildOption(id, name);
    option.selected = true;
    select.appendChild(option);
}

function appendOptionToNamespace(namespace, selectName, id, name) {
    const rowsBody = document.querySelector(`tbody[data-${namespace}-rows]`);
    if (!rowsBody) {
        return;
    }
    Array.from(rowsBody.querySelectorAll(`select[name="${selectName}"]`)).forEach((select) => {
        appendOptionTo(select, id, name);
    });
}

// Selects the newly created record into the first row that doesn't already have a
// selection; if every row is taken, a new row is added (via the section's own
// "Add …" button) and the selection is placed there instead of overwriting an
// existing row's choice.
function selectIntoNamespace(namespace, selectName, id, name) {
    const rowsBody = document.querySelector(`tbody[data-${namespace}-rows]`);
    if (!rowsBody) {
        return;
    }

    // The "Add …" button starts disabled when its source list (e.g. people) is
    // empty. Having just created a record for that list, re-enable it before
    // possibly relying on it below to add a row.
    const addButton = document.getElementById(`add-${namespace}`);
    if (addButton && addButton.disabled) {
        addButton.disabled = false;
        addButton.classList.remove('opacity-50', 'cursor-not-allowed');
    }

    const rowSelects = Array.from(rowsBody.querySelectorAll(`tr[data-${namespace}-row] select[name="${selectName}"]`));
    let target = rowSelects.find((select) => !select.value);

    if (!target) {
        if (addButton) {
            addButton.click();
        }
        const refreshed = Array.from(rowsBody.querySelectorAll(`tr[data-${namespace}-row] select[name="${selectName}"]`));
        target = refreshed[refreshed.length - 1];
    }

    if (target) {
        selectValue(target, id, name);
    }
}

export function buildVenueFallback(id, name) {
    const placeholder = document.getElementById('venueId-empty-placeholder');
    if (!placeholder) {
        return;
    }

    const select = document.createElement('select');
    select.name = 'venueId';
    select.id = 'venueId';
    select.className = 'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500';

    const blank = document.createElement('option');
    blank.value = '';
    blank.textContent = 'Select a venue';
    select.appendChild(blank);
    select.appendChild(buildOption(id, name));
    select.value = String(id);

    placeholder.replaceWith(select);
}

// The sponsorship table (like the venue select) isn't rendered at all when the
// production starts with zero sponsors, so there is nowhere for a rowset select to
// attach to until one exists. Build the same markup the "not empty" branch of the
// template renders, wire up its Delete link, and re-enable the Add Sponsorship button.
export function buildSponsorshipTableFallback(id, name) {
    const placeholder = document.getElementById('sponsorship-empty-placeholder');
    if (!placeholder) {
        return;
    }

    const wrapper = document.createElement('div');
    wrapper.className = 'relative overflow-x-auto bg-white border border-gray-200 shadow-sm rounded-md';
    wrapper.innerHTML = `
        <table class="w-full text-sm text-left text-gray-700">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 font-medium">Sponsor</th>
                    <th class="px-4 py-3 font-medium text-right">Action</th>
                </tr>
            </thead>
            <tbody data-sponsorship-rows>
                <tr class="bg-white border-b border-gray-100" data-sponsorship-row>
                    <th scope="row" class="px-4 py-3 font-medium text-gray-900">
                        <select name="sponsorshipSponsorIds[]" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Select a sponsor</option>
                        </select>
                    </th>
                    <td class="px-4 py-3 text-right">
                        <a href="#" class="delete-sponsorship font-medium text-blue-600 hover:underline">Delete</a>
                    </td>
                </tr>
                <tr class="hidden bg-white border-b border-gray-100" data-sponsorship-template>
                    <th scope="row" class="px-4 py-3 font-medium text-gray-900">
                        <select name="sponsorshipSponsorIds[]" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Select a sponsor</option>
                        </select>
                    </th>
                    <td class="px-4 py-3 text-right">
                        <a href="#" class="delete-sponsorship font-medium text-blue-600 hover:underline">Delete</a>
                    </td>
                </tr>
            </tbody>
        </table>
    `;

    placeholder.replaceWith(wrapper);

    const select = wrapper.querySelector('tr[data-sponsorship-row] select');
    if (select) {
        select.appendChild(buildOption(id, name));
        select.value = String(id);
    }

    const addButton = document.getElementById('add-sponsorship');
    if (addButton) {
        addButton.disabled = false;
        addButton.classList.remove('opacity-50', 'cursor-not-allowed');
    }
}

function applyHandler(handler, id, name) {
    if (handler.type === 'single') {
        populateSingleSelect(handler.selectName, id, name, handler.buildFallback);
    } else if (handler.type === 'multiselect') {
        populateMultiSelect(handler.id, id, name);
    } else if (handler.type === 'rowset') {
        const rowsBody = document.querySelector(`tbody[data-${handler.namespace}-rows]`);
        if (!rowsBody) {
            if (typeof handler.buildFallback === 'function') {
                handler.buildFallback(id, name);
            }
            return;
        }
        appendOptionToNamespace(handler.namespace, handler.selectName, id, name);
        selectIntoNamespace(handler.namespace, handler.selectName, id, name);
    } else if (handler.type === 'rowset-group') {
        handler.candidates.forEach((candidate) => {
            appendOptionToNamespace(candidate.namespace, candidate.selectName, id, name);
        });

        const contextValue = handler.contextAttr ? document.body.dataset[handler.contextAttr] : null;
        const target = handler.candidates.find((candidate) => candidate.namespace === contextValue) || handler.candidates[0];
        selectIntoNamespace(target.namespace, target.selectName, id, name);
    }
}

// The project pins htmx 1.9.2, which predates the hx-on:<event> / hx-on::<htmx-event>
// attribute shorthand (only the older single hx-on="event:code" attribute is
// supported by that version's attribute scanner). The "+ New …" buttons therefore
// can't declare `hx-on::after-request="…showModal()…"` inline — that attribute is
// silently inert on 1.9.2, so the modal's content loads but the dialog never opens.
// htmx:afterRequest itself is core functionality present in every version, so we
// open the modal from a real listener instead, keyed off the swap target.
function openModalAfterQuickCreateLoad(event) {
    var target = event.detail && event.detail.target;
    if (!target || target.id !== 'quick-create-modal-content') {
        return;
    }
    var modal = document.getElementById('quick-create-modal');
    if (!modal) {
        return;
    }

    // Nested case: the "Add a Person/Venue first" button inside an already-open
    // modal swaps #quick-create-modal-content again without closing the dialog.
    // Calling showModal() on an already-open <dialog> throws, so skip it then.
    if (typeof modal.showModal === 'function' && !modal.open) {
        modal.showModal();
    }

    // Focus the form's first field ourselves instead of relying on an
    // `autofocus` attribute on the swapped-in markup: showModal()'s native
    // autofocus handling can trigger a page-level scroll-into-view before the
    // dialog has actually finished becoming a top-layer element, which is what
    // was causing the page to jump to the top on every "+ New …" click.
    // preventScroll keeps the focus purely inside the (already on-screen) dialog.
    var firstField = target.querySelector('input, select, textarea');
    if (firstField && typeof firstField.focus === 'function') {
        firstField.focus({ preventScroll: true });
    }
}

// config maps entityCreated "type" values (person | work | venue | sponsor) to a
// handler descriptor. See create.html.twig / edit.html.twig for the shapes in use.
// How long the "X was added." success alert stays visible in the modal before
// it auto-closes. Long enough to read a short message, short enough to still
// feel instant for the "just let me get back to the production form" flow.
const SUCCESS_CLOSE_DELAY_MS = 900;

export function initQuickCreateHandlers(config) {
    document.body.addEventListener('htmx:afterRequest', openModalAfterQuickCreateLoad);

    document.body.addEventListener('entityCreated', function (event) {
        const { id, name, type } = event.detail;
        const handler = config[type];
        if (handler) {
            applyHandler(handler, id, name);
        }

        // The success alert was just swapped into #modal-flash by the same
        // response that triggered this event; give it a moment on screen
        // before closing the modal instead of closing instantly.
        window.setTimeout(function () {
            const modal = document.getElementById('quick-create-modal');
            if (modal && modal.open) {
                modal.close();
            }
        }, SUCCESS_CLOSE_DELAY_MS);
    });
}
