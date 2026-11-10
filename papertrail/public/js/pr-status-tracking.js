(() => {
    const panel = document.querySelector('[data-pr-activity]');
    const filter = panel?.querySelector('[data-pr-activity-filter]');
    if (!filter) return;
    const items = [...panel.querySelectorAll('[data-pr-activity-state]')];
    const empty = panel.querySelector('[data-pr-activity-empty]');
    filter.addEventListener('change', () => {
        let visible = 0;
        items.forEach((item) => {
            item.hidden = filter.value !== 'all' && item.dataset.prActivityState !== filter.value;
            if (!item.hidden) visible += 1;
        });
        empty.hidden = visible > 0 || items.length === 0;
    });
})();
