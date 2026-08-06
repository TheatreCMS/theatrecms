function isInsideTemplateRow(el) {
    let node = el;
    while (node && node !== document.body) {
        if (node.attributes) {
            for (let i = 0; i < node.attributes.length; i++) {
                if (/-template$/.test(node.attributes[i].name)) {
                    return true;
                }
            }
        }
        node = node.parentElement;
    }
    return false;
}

// Wraps a plain <select class="searchable-select"> in a type-to-filter text input
// backed by a <datalist>, keeping the original select (hidden) in sync so existing
// form submission/validation code keeps working unchanged.
//
// Exposed on window so dynamically-added rows (see the "Add …" row-cloning logic in
// the production create/edit pages) can enhance a fresh, never-enhanced select on
// demand — cloning an already-enhanced select carries over its HTML but not the
// event listeners bound here, which would silently leave the clone's value stuck
// empty no matter what the user picks.
function enhanceSearchableSelect(select) {
    if (select.dataset.searchableEnhanced === 'true') {
        return;
    }

    select.dataset.searchableEnhanced = 'true';

    const wrapper = document.createElement('div');
    wrapper.className = 'space-y-1';
    const parent = select.parentNode;
    if (!parent) {
        return;
    }

    parent.insertBefore(wrapper, select);
    wrapper.appendChild(select);

    const input = document.createElement('input');
    const selectClasses = Array.from(select.classList)
        .filter((cls) => cls !== 'searchable-select')
        .join(' ');

    input.type = 'text';
    input.className = selectClasses || 'w-full rounded-md border-gray-300 shadow-sm';
    input.autocomplete = 'off';
    input.setAttribute('placeholder', select.dataset.searchPlaceholder || 'Search...');
    input.setAttribute('aria-label', select.getAttribute('aria-label') || select.name || 'Select an option');

    if (select.id) {
        input.id = select.id;
        select.removeAttribute('id');
    }

    if (select.getAttribute('required') !== null) {
        input.required = true;
    }

    if (select.getAttribute('disabled') !== null) {
        input.disabled = true;
    }

    if (select.getAttribute('aria-describedby')) {
        input.setAttribute('aria-describedby', select.getAttribute('aria-describedby'));
    }

    if (select.getAttribute('title')) {
        input.setAttribute('title', select.getAttribute('title'));
    }

    const datalistId = `searchable-select-${Math.random().toString(36).slice(2)}`;
    const datalist = document.createElement('datalist');
    datalist.id = datalistId;

    Array.from(select.options).forEach((option) => {
        const text = option.textContent.trim();
        const value = option.value.trim();

        if (!text || !value) {
            return;
        }

        const entry = document.createElement('option');
        entry.value = text;
        entry.dataset.optionValue = value;
        datalist.appendChild(entry);
    });

    wrapper.appendChild(input);
    wrapper.appendChild(datalist);
    input.setAttribute('list', datalistId);

    const syncInputFromSelect = () => {
        const selectedOption = Array.from(select.options).find((option) => option.value === select.value && option.value !== '');
        input.value = selectedOption ? selectedOption.textContent.trim() : '';
    };

    const syncSelectFromInput = (eventType = 'input') => {
        const typedValue = input.value.trim();

        if (!typedValue) {
            select.value = '';
            syncInputFromSelect();
            return;
        }

        const matchingOption = Array.from(select.options).find((option) => {
            const optionText = option.textContent.trim().toLowerCase();
            return optionText === typedValue.toLowerCase() && option.value !== '';
        });

        if (matchingOption) {
            select.value = matchingOption.value;
            syncInputFromSelect();
            return;
        }

        if (eventType === 'change' || eventType === 'blur') {
            syncInputFromSelect();
        }
    };

    input.addEventListener('input', () => syncSelectFromInput('input'));
    input.addEventListener('change', () => syncSelectFromInput('change'));
    input.addEventListener('blur', () => syncSelectFromInput('blur'));
    select.addEventListener('change', syncInputFromSelect);

    syncInputFromSelect();
    select.style.display = 'none';
}

window.enhanceSearchableSelect = enhanceSearchableSelect;

document.addEventListener('DOMContentLoaded', function () {
    const selects = Array.from(document.querySelectorAll('select.searchable-select:not([multiple])'));

    selects.forEach((select) => {
        // Hidden template rows (cloned on demand by "Add …" buttons) are enhanced
        // fresh at clone time instead — see enhanceSearchableSelect() above.
        if (isInsideTemplateRow(select)) {
            return;
        }

        enhanceSearchableSelect(select);
    });
});
