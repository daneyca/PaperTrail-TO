(() => {
    const number = document.querySelector('[data-official-pr-number]');
    const date = document.querySelector('[data-official-pr-date]');
    const example = document.querySelector('[data-official-pr-example]');
    const fund = number?.dataset.fundCode;
    if (!fund || !date || !example) return;
    const updateExample = () => {
        if (!/^\d{4}-(0[1-9]|1[0-2])-\d{2}$/.test(date.value)) return;
        const period = date.value.slice(0, 7);
        number.placeholder = `${fund}-${period}-001`;
        number.pattern = `${fund}-${period}-[0-9]{3,9}`;
        example.textContent = number.placeholder;
    };
    date.addEventListener('input', updateExample);
    date.addEventListener('change', updateExample);
    updateExample();
})();
