(() => {
    const number = document.querySelector('[data-official-pr-number]');
    const date = document.querySelector('[data-official-pr-date]');
    const help = document.querySelector('[data-official-pr-help]');
    if (!number?.dataset.fundCode || !date || !help) return;

    let pending;
    const updatePreview = async () => {
        pending?.abort();
        number.value = '';
        if (!date.value || !date.validity.valid) {
            number.placeholder = 'Choose a valid PR date';
            help.textContent = 'Choose the official PR date to see the next number.';
            return;
        }

        const request = new AbortController();
        pending = request;
        const url = new URL(number.dataset.previewUrl, window.location.href);
        url.searchParams.set('pr_date', date.value);
        number.placeholder = 'Loading next number…';
        help.textContent = 'Checking the next available PR number…';

        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
                signal: request.signal,
            });
            if (!response.ok) throw new Error('Preview unavailable');
            const result = await response.json();
            if (request.signal.aborted) return;
            number.value = result.number ?? '';
            number.placeholder = 'Fund source required';
            help.textContent = result.number
                ? 'The system assigns the next available number when you save. No typing needed.'
                : 'Return this PR for correction of its fund source.';
        } catch (error) {
            if (request.signal.aborted) return;
            number.placeholder = 'Assigned automatically when saved';
            help.textContent = 'The preview could not load. The system will generate the number when you save.';
        }
    };
    date.addEventListener('input', updatePreview);
    date.addEventListener('change', updatePreview);
})();
