document.addEventListener('DOMContentLoaded', () => {
    const select = document.querySelector('[data-app-create-year]');
    const data = document.querySelector('[data-app-year-plans]');
    if (!select || !data) return;
    const plans = JSON.parse(data.textContent);
    const status = document.querySelector('[data-app-year-status]');
    const title = document.querySelector('[data-app-year-title]');
    const help = document.querySelector('[data-app-year-help]');
    const action = document.querySelector('[data-app-year-action]');
    const supplemental = document.querySelector('[data-app-year-supplemental]');
    const update = () => {
        const year = select.value;
        const plan = plans[year];
        status.textContent = plan ? `APP exists · ${plan.status_label}` : 'Available to create';
        status.className = `status-pill ${plan ? 'status-submitted' : 'status-approved'}`;
        title.textContent = plan ? plan.number : `No active APP exists for CY ${year}`;
        action.textContent = `${plan ? (plan.editable ? 'Continue APP' : 'View APP') : 'Create APP'} for ${year}`;
        supplemental.hidden = !plan?.supplemental_url;
        supplemental.style.display = plan?.supplemental_url ? '' : 'none';
        if (plan?.supplemental_url) {
            supplemental.href = plan.supplemental_url;
            supplemental.textContent = `Create Supplemental APP for ${year}`;
        } else {
            supplemental.removeAttribute('href');
        }
        help.textContent = !plan
            ? `You can create a new main APP for ${year}. Planning can begin before that year starts.`
            : plan.editable
                ? `Continue the existing APP for ${year}. A second main APP for the same year is not needed.`
                : plan.status === 'approved'
                    ? `The APP for ${year} is already approved. Use Supplemental APP for additional purchases, or select another fiscal year for a new main APP.`
                    : `An APP for ${year} already exists. Open it to check its progress, or select another fiscal year for a new main APP.`;
    };
    select.addEventListener('change', update);
    update();
});
