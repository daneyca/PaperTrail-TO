(() => {
    const form = document.querySelector('[data-global-search]');
    if (!form) return;

    const input = form.querySelector('input[name="q"]');
    const list = form.querySelector('.global-search-suggestions');
    const suggestionsUrl = form.dataset.suggestionsUrl;
    let timer;
    let controller;
    let activeIndex = -1;

    const close = () => {
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        activeIndex = -1;
    };

    const setActive = (index) => {
        const options = [...list.querySelectorAll('[role="option"]')];
        options.forEach((option) => option.classList.remove('is-active'));
        activeIndex = index;
        if (options[index]) {
            options[index].classList.add('is-active');
            input.setAttribute('aria-activedescendant', options[index].id);
        } else {
            input.removeAttribute('aria-activedescendant');
        }
    };

    const render = (results, term) => {
        list.replaceChildren();
        if (results.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'global-search-suggestions__empty';
            empty.textContent = 'No matching records';
            list.append(empty);
        }

        results.forEach((result, index) => {
            const link = document.createElement('a');
            link.href = result.url;
            link.id = `global-search-option-${index}`;
            link.role = 'option';
            link.className = 'global-search-suggestion';

            const type = document.createElement('span');
            type.className = 'global-search-suggestion__type';
            type.textContent = result.type;
            const label = document.createElement('span');
            label.className = 'global-search-suggestion__text';
            label.textContent = `${result.reference} ${result.title}`;
            link.append(type, label);
            list.append(link);
        });

        const all = document.createElement('a');
        all.className = 'global-search-suggestions__all';
        all.href = `${form.action}?q=${encodeURIComponent(term)}`;
        all.textContent = 'View all results';
        list.append(all);
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        setActive(-1);
    };

    input.addEventListener('input', () => {
        clearTimeout(timer);
        controller?.abort();
        const term = input.value.trim();
        if (term.length < 2) {
            close();
            return;
        }

        timer = setTimeout(async () => {
            controller = new AbortController();
            try {
                const response = await fetch(`${suggestionsUrl}?q=${encodeURIComponent(term)}`, {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });
                if (!response.ok || input.value.trim() !== term) return;
                const payload = await response.json();
                render(payload.results || [], term);
            } catch (error) {
                if (error.name !== 'AbortError') close();
            }
        }, 250);
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            close();
            return;
        }
        if (list.hidden) return;
        const options = [...list.querySelectorAll('[role="option"]')];
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            setActive(event.key === 'ArrowDown'
                ? Math.min(activeIndex + 1, options.length - 1)
                : Math.max(activeIndex - 1, -1));
        } else if (event.key === 'Enter' && activeIndex >= 0) {
            event.preventDefault();
            window.location.assign(options[activeIndex].href);
        }
    });

    document.addEventListener('click', (event) => {
        if (!form.contains(event.target)) close();
    });
    form.addEventListener('submit', () => {
        clearTimeout(timer);
        controller?.abort();
    });
})();
