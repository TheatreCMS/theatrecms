// Dual-listbox "works" picker for the production edit page.
//
// Renders a searchable "available" column and a "selected" column on top of a
// hidden <select multiple name="works[]"> and keeps that select as the single
// source of truth, so the form still submits works[] exactly the way
// ProductionController::update() already expects — no server-side changes.
//
// Order matters here (e.g. a choir's setlist), so the DOM order of the
// selected <option> elements is also the source of truth for that order:
// native multi-select form submission serializes selected options in DOM
// document order, and ProductionController::syncWorks() persists works[] in
// the order it's submitted. Reordering the "Selected" column therefore has to
// physically move <option> nodes, not just re-render the visual list.
//
// A MutationObserver watches the hidden select for new <option> children so
// the widget stays in sync when production-quick-create.js's "+ New Work"
// flow appends a freshly created work (it targets #works by id already).

function buildItem(option, { removable }) {
    const li = document.createElement('li');
    li.dataset.value = option.value;
    li.setAttribute('role', 'option');
    li.setAttribute('tabindex', '0');
    li.setAttribute('draggable', 'true');
    li.setAttribute('aria-selected', removable ? 'true' : 'false');
    li.className = 'flex items-center justify-between gap-2 px-2 py-1.5 text-sm rounded cursor-grab select-none hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500';

    const label = document.createElement('span');
    label.textContent = option.textContent;
    label.className = 'truncate';
    li.appendChild(label);

    if (removable) {
        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'flex-shrink-0 text-gray-400 hover:text-red-600 px-1 rounded focus:outline-none focus:ring-2 focus:ring-red-400';
        removeButton.setAttribute('aria-label', `Remove ${option.textContent}`);
        removeButton.textContent = '×';
        li.appendChild(removeButton);
    }

    return li;
}

function initWorksPicker(container) {
    const select = container.querySelector('[data-works-source]');
    const searchInput = container.querySelector('[data-works-search]');
    const availableList = container.querySelector('[data-works-available]');
    const selectedList = container.querySelector('[data-works-selected]');
    const availableEmpty = container.querySelector('[data-works-available-empty]');
    const selectedEmpty = container.querySelector('[data-works-selected-empty]');
    const countEl = container.querySelector('[data-works-selected-count]');
    const announcer = container.querySelector('[data-works-announcer]');

    if (!select || !searchInput || !availableList || !selectedList) {
        return;
    }

    function announce(message) {
        if (announcer) {
            announcer.textContent = message;
        }
    }

    function findOption(value) {
        return Array.from(select.options).find((option) => option.value === value) || null;
    }

    // The order of currently-selected values, taken from the last render — i.e.
    // the order the user currently sees in the "Selected" column.
    function currentSelectedOrder() {
        return Array.from(selectedList.children).map((li) => li.dataset.value);
    }

    // Physically reorders the given values' <option> nodes to the end of the
    // select's children, in the order given — this is what makes native form
    // submission (and the next render()) reflect the new order.
    function applyOption(orderedValues) {
        orderedValues.forEach((value) => {
            const option = findOption(value);
            if (option) {
                select.appendChild(option);
            }
        });
        select.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function setSelected(option, selected) {
        if (option.selected === selected) {
            return;
        }
        option.selected = selected;
        if (selected) {
            applyOption([...currentSelectedOrder().filter((v) => v !== option.value), option.value]);
        } else {
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
        announce(`${option.textContent} ${selected ? 'added to' : 'removed from'} selected works.`);
        render();
    }

    function toggle(value) {
        const option = findOption(value);
        if (option) {
            setSelected(option, !option.selected);
        }
    }

    function moveSelected(value, delta) {
        const order = currentSelectedOrder();
        const index = order.indexOf(value);
        if (index === -1) {
            return;
        }
        const newIndex = index + delta;
        if (newIndex < 0 || newIndex >= order.length) {
            return;
        }
        order.splice(index, 1);
        order.splice(newIndex, 0, value);
        applyOption(order);

        const option = findOption(value);
        if (option) {
            announce(`${option.textContent} moved to position ${newIndex + 1} of ${order.length}.`);
        }
        render();
    }

    function attachCommonHandlers(li, value) {
        li.addEventListener('click', () => toggle(value));
        li.addEventListener('dragstart', (event) => {
            event.dataTransfer.setData('text/plain', value);
            event.dataTransfer.effectAllowed = 'move';
            li.classList.add('opacity-40');
        });
        li.addEventListener('dragend', () => li.classList.remove('opacity-40'));
    }

    function render() {
        const focusedLi = document.activeElement ? document.activeElement.closest('li[data-value]') : null;
        const focusedValue = focusedLi && container.contains(focusedLi) ? focusedLi.dataset.value : null;

        const query = searchInput.value.trim().toLowerCase();
        availableList.innerHTML = '';
        selectedList.innerHTML = '';

        const options = Array.from(select.options);
        let selectedCount = 0;

        options.forEach((option) => {
            if (option.selected) {
                selectedCount += 1;
                const li = buildItem(option, { removable: true });
                attachCommonHandlers(li, option.value);
                li.querySelector('button').addEventListener('click', (event) => {
                    event.stopPropagation();
                    setSelected(option, false);
                });
                li.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        toggle(option.value);
                    } else if (event.altKey && (event.key === 'ArrowUp' || event.key === 'ArrowDown')) {
                        event.preventDefault();
                        moveSelected(option.value, event.key === 'ArrowUp' ? -1 : 1);
                    }
                });
                selectedList.appendChild(li);
            }
        });

        options
            .filter((option) => !option.selected && (!query || option.textContent.toLowerCase().includes(query)))
            .sort((a, b) => a.textContent.localeCompare(b.textContent))
            .forEach((option) => {
                const li = buildItem(option, { removable: false });
                attachCommonHandlers(li, option.value);
                li.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        toggle(option.value);
                    }
                });
                availableList.appendChild(li);
            });

        if (availableEmpty) {
            availableEmpty.classList.toggle('hidden', availableList.children.length > 0);
        }
        if (selectedEmpty) {
            selectedEmpty.classList.toggle('hidden', selectedList.children.length > 0);
        }
        if (countEl) {
            countEl.textContent = `(${selectedCount})`;
        }

        if (focusedValue) {
            const toRefocus = container.querySelector(`li[data-value="${CSS.escape(focusedValue)}"]`);
            if (toRefocus) {
                toRefocus.focus();
            }
        }
    }

    // Available column: dropping a selected item here just removes it, order among
    // unselected items doesn't matter (the column is always re-sorted by title).
    function setupAvailableDropZone(list) {
        list.addEventListener('dragover', (event) => {
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            list.classList.add('ring-2', 'ring-blue-400');
        });
        list.addEventListener('dragleave', () => {
            list.classList.remove('ring-2', 'ring-blue-400');
        });
        list.addEventListener('drop', (event) => {
            event.preventDefault();
            list.classList.remove('ring-2', 'ring-blue-400');
            const option = findOption(event.dataTransfer.getData('text/plain'));
            if (option) {
                setSelected(option, false);
            }
        });
    }

    // Selected column: also a sortable list — dropping (from either column) inserts
    // at the position under the cursor, so items already selected can be reordered.
    function insertionIndexFor(list, clientY) {
        const items = Array.from(list.children);
        for (let i = 0; i < items.length; i += 1) {
            const rect = items[i].getBoundingClientRect();
            if (clientY < rect.top + rect.height / 2) {
                return i;
            }
        }
        return items.length;
    }

    function setupSelectedDropZone(list) {
        list.addEventListener('dragover', (event) => {
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            list.classList.add('ring-2', 'ring-blue-400');

            const index = insertionIndexFor(list, event.clientY);
            Array.from(list.children).forEach((li, i) => {
                li.classList.toggle('border-t-2', i === index);
                li.classList.toggle('border-blue-500', i === index);
            });
            if (index === list.children.length && list.lastElementChild) {
                list.lastElementChild.classList.add('border-b-2', 'border-blue-500');
            }
        });
        list.addEventListener('dragleave', (event) => {
            if (!list.contains(event.relatedTarget)) {
                list.classList.remove('ring-2', 'ring-blue-400');
                Array.from(list.children).forEach((li) => li.classList.remove('border-t-2', 'border-b-2', 'border-blue-500'));
            }
        });
        list.addEventListener('drop', (event) => {
            event.preventDefault();
            list.classList.remove('ring-2', 'ring-blue-400');
            Array.from(list.children).forEach((li) => li.classList.remove('border-t-2', 'border-b-2', 'border-blue-500'));

            const value = event.dataTransfer.getData('text/plain');
            const option = findOption(value);
            if (!option) {
                return;
            }

            const index = insertionIndexFor(list, event.clientY);
            const order = currentSelectedOrder().filter((v) => v !== value);
            order.splice(index, 0, value);

            option.selected = true;
            applyOption(order);
            announce(`${option.textContent} moved to position ${order.indexOf(value) + 1} of ${order.length}.`);
            render();
        });
    }

    setupAvailableDropZone(availableList);
    setupSelectedDropZone(selectedList);

    searchInput.addEventListener('input', render);

    new MutationObserver(render).observe(select, { childList: true });

    render();
}

function init() {
    document.querySelectorAll('[data-works-picker]').forEach(initWorksPicker);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
