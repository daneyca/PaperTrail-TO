(() => {
    const search = document.querySelector('[data-list-search]');
    if (!search) return;

    const input = search.querySelector('input[type="search"]');
    const scope = search.querySelector('[data-search-scope]');
    const submit = search.querySelector('button[type="submit"]');
    const terms = { page: input.value, global: '' };
    let currentScope = scope.value;

    scope.addEventListener('change', () => {
        terms[currentScope] = input.value;
        currentScope = scope.value;
        const isPage = currentScope === 'page';
        search.action = isPage ? search.dataset.listAction : search.dataset.globalAction;
        input.name = isPage ? 'search' : 'q';
        input.value = terms[currentScope];
        input.placeholder = isPage ? 'Tracking number or title…' : 'Search all records…';
        const label = isPage ? `Search ${search.dataset.listLabel} by tracking number or title` : 'Search all procurement records';
        input.setAttribute('aria-label', label);
        input.setAttribute('aria-autocomplete', isPage ? 'none' : 'list');
        submit.setAttribute('aria-label', isPage ? `Search ${search.dataset.listLabel}` : 'Search all records');
        search.querySelectorAll('[data-list-search-filter]').forEach(field => { field.disabled = !isPage; });
        input.dispatchEvent(new Event('input'));
    });

    document.querySelectorAll('[data-open-list-filters]').forEach(trigger => {
        const dialog = document.getElementById(trigger.dataset.openListFilters);
        if (!dialog) return;
        const form = dialog.querySelector('[data-list-filter-form]');
        const from = form.elements.namedItem('date_from');
        const to = form.elements.namedItem('date_to');
        const validateDates = () => {
            to?.setCustomValidity(from?.value && to.value && from.value > to.value
                ? 'Choose an end date on or after the start date.' : '');
        };
        from?.addEventListener('input', validateDates);
        to?.addEventListener('input', validateDates);
        form.addEventListener('submit', event => {
            validateDates();
            if (!form.reportValidity()) event.preventDefault();
            form.querySelector('[data-list-filter-search]').value = input.value;
        });
        trigger.addEventListener('click', () => {
            if (scope.value !== 'page') {
                scope.value = 'page';
                scope.dispatchEvent(new Event('change'));
            }
            form.reset();
            validateDates();
            form.querySelector('[data-list-filter-search]').value = input.value;
            dialog.showModal();
            trigger.setAttribute('aria-expanded', 'true');
        });
        dialog.querySelectorAll('[data-close-list-filters]').forEach(button => {
            button.addEventListener('click', () => dialog.close());
        });
        dialog.addEventListener('click', event => {
            const bounds = dialog.getBoundingClientRect();
            if (event.target === dialog && (event.clientX < bounds.left || event.clientX > bounds.right
                || event.clientY < bounds.top || event.clientY > bounds.bottom)) dialog.close();
        });
        dialog.addEventListener('close', () => {
            trigger.setAttribute('aria-expanded', 'false');
            trigger.focus();
        });
    });
})();
