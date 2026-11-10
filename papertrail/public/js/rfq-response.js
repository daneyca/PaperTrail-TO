document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-copy-rfq-link]').forEach(button => {
        button.addEventListener('click', async () => {
            const link = button.dataset.copyRfqLink;
            try {
                await navigator.clipboard.writeText(link);
                button.textContent = 'Link Copied';
            } catch {
                window.prompt('Copy this private supplier response link:', link);
            }
        });
    });
    const form = document.querySelector('[data-rfq-response-form]');
    if (!form) return;
    const money = value => value.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const updatePrices = () => {
        let total = 0;
        let hasPrices = false;
        form.querySelectorAll('[data-quote-item]').forEach(row => {
            const input = row.querySelector('[data-unit-price]');
            const price = Number(input.value);
            const valid = input.value !== '' && Number.isFinite(price) && price >= 0;
            const line = valid ? Math.round((price * Number(row.dataset.quantity) + Number.EPSILON) * 100) / 100 : 0;
            row.querySelector('[data-line-total]').textContent = valid ? money(line) : '—';
            total += line;
            hasPrices ||= valid;
        });
        form.querySelector('[data-quote-total]').textContent = hasPrices ? money(total) : '—';
    };
    form.querySelectorAll('[data-unit-price]').forEach(input => input.addEventListener('input', updatePrices));
    updatePrices();
    const canvas = form.querySelector('[data-signature-pad]');
    const context = canvas.getContext('2d');
    const field = form.querySelector('[data-signature-data]');
    let drawing = false;
    let hasInk = false;
    context.lineWidth = 3;
    context.lineCap = 'round';
    context.lineJoin = 'round';
    context.strokeStyle = '#102c50';
    const position = event => {
        const bounds = canvas.getBoundingClientRect();
        return [(event.clientX - bounds.left) * canvas.width / bounds.width, (event.clientY - bounds.top) * canvas.height / bounds.height];
    };
    if (field.value.startsWith('data:image/png;base64,')) {
        const previous = new Image();
        previous.onload = () => { context.drawImage(previous, 0, 0, canvas.width, canvas.height); hasInk = true; };
        previous.src = field.value;
    }
    canvas.addEventListener('pointerdown', event => {
        event.preventDefault();
        canvas.setPointerCapture(event.pointerId);
        drawing = true;
        context.beginPath();
        context.moveTo(...position(event));
    });
    canvas.addEventListener('pointermove', event => {
        if (!drawing) return;
        event.preventDefault();
        context.lineTo(...position(event));
        context.stroke();
        hasInk = true;
        field.value = canvas.toDataURL('image/png');
    });
    const stop = () => { drawing = false; if (hasInk) field.value = canvas.toDataURL('image/png'); };
    canvas.addEventListener('pointerup', stop);
    canvas.addEventListener('pointercancel', stop);
    form.querySelector('[data-clear-signature]').addEventListener('click', () => {
        context.clearRect(0, 0, canvas.width, canvas.height);
        hasInk = false;
        field.value = '';
    });
    form.addEventListener('submit', event => {
        if (!hasInk || !field.value) {
            event.preventDefault();
            window.alert('Please draw your signature before submitting the quotation.');
            canvas.scrollIntoView({ block: 'center', behavior: 'smooth' });
        } else if (!window.confirm('Submit this signed quotation? It will be locked for staff review.')) {
            event.preventDefault();
        }
    });
});
