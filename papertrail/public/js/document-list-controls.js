(() => {
    const search = document.querySelector('[data-list-search]');
    const input = search?.querySelector('input[type="search"]');

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
            const keyword = form.querySelector('[data-list-filter-search]');
            if (keyword && input) keyword.value = input.value;
        });
        trigger.addEventListener('click', () => {
            form.reset();
            validateDates();
            const keyword = form.querySelector('[data-list-filter-search]');
            if (keyword && input) keyword.value = input.value;
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
