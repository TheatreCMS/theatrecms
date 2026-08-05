document.addEventListener('DOMContentLoaded', function () {
    const selects = Array.from(document.querySelectorAll('select.searchable-select:not([multiple])'));

    selects.forEach((select) => {
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
    });
});
