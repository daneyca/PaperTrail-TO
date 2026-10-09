@php
    $templateKey = $templateKey ?? 'competitive_bidding_document';
    $documentTitle = $documentTitle ?? 'Competitive Bidding Document';
@endphp

<style>
    .cb-routing-panel {
        display: flex;
        align-items: end;
        justify-content: flex-end;
        gap: 12px;
        margin-bottom: 16px;
        padding: 14px 16px;
        border: 1px solid #d8dee8;
        border-radius: 8px;
        background: #fff;
        box-shadow: 0 10px 28px rgba(11, 35, 65, 0.06);
    }

    .cb-routing-panel label {
        display: grid;
        gap: 5px;
        min-width: 220px;
        color: #52647d;
        font: 800 0.72rem/1.2 Figtree, Arial, sans-serif;
        text-transform: uppercase;
    }

    .cb-routing-panel input {
        min-height: 38px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 0 11px;
        color: #0b2341;
        font: 700 0.86rem/1.2 Figtree, Arial, sans-serif;
        text-transform: none;
    }

    .cb-routing-panel button,
    .cb-routing-panel a {
        display: inline-flex;
        min-height: 38px;
        align-items: center;
        justify-content: center;
        padding: 0 14px;
        border: 1px solid #0b2341;
        border-radius: 8px;
        background: #0b2341;
        color: #fff;
        font: 800 0.84rem/1 Figtree, Arial, sans-serif;
        text-decoration: none;
        cursor: pointer;
    }

    .cb-routing-panel a {
        border-color: #cbd5e1;
        background: #fff;
        color: #0b2341;
    }

    .cb-routing-alert {
        margin-bottom: 12px;
        padding: 12px 14px;
        border: 1px solid #bfdbfe;
        border-radius: 8px;
        background: #eff6ff;
        color: #12345a;
        font: 700 0.86rem/1.35 Figtree, Arial, sans-serif;
    }

    .cb-routing-alert.is-error {
        border-color: #fecaca;
        background: #fff1f2;
        color: #991b1b;
    }

    .cb-signatory-role-print {
        display: none !important;
    }

    .cb-signatory-role-select {
        display: block;
        width: max-content;
        min-width: 138px;
        max-width: 100%;
        margin-top: 2px;
        border: 1px solid #93c5fd;
        border-radius: 4px;
        background: #eff6ff;
        padding: 2px 20px 2px 4px;
        color: #0b2341 !important;
        font: 700 7pt/1.15 Arial, Helvetica, sans-serif !important;
    }

    .cb-supplier-route-row {
        margin-top: 8px;
    }

    @media print {
        .cb-routing-panel,
        .cb-routing-alert,
        .cb-signatory-role-select {
            display: none !important;
        }

        .cb-signatory-role-print {
            display: block !important;
        }
    }
</style>

@if (session('status'))
    <div class="cb-routing-alert no-print">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="cb-routing-alert is-error no-print">{{ $errors->first() }}</div>
@endif

<form
    method="POST"
    action="{{ route('bac-secretariat.competitive-bidding.signature-documents.store') }}"
    class="cb-routing-panel no-print"
    data-cb-routing-form="{{ $templateKey }}"
>
    @csrf
    <input type="hidden" name="template_key" value="{{ $templateKey }}">
    <input type="hidden" name="document_title" value="{{ $documentTitle }}">
    <input type="hidden" name="document_html" data-cb-document-html>
    <input type="hidden" name="signatories" data-cb-signatories>

    <label>
        Reference No.
        <input type="text" name="tracking_number" placeholder="Optional">
    </label>

    <a href="{{ route('supplier-signatures.index') }}">Supplier Links</a>
    <button type="submit">Forward Signatories</button>
</form>

<script>
    (() => {
        const form = document.querySelector('[data-cb-routing-form="{{ $templateKey }}"]');

        if (!form) {
            return;
        }

        const textOf = (node) => (node?.innerText || node?.textContent || '').replace(/\s+/g, ' ').trim();

        document.querySelectorAll('[data-cb-signatory-role]').forEach((select) => {
            const sync = () => {
                const wrapper = select.closest('[data-cb-signatory]') || select.parentElement;
                const printRole = wrapper?.querySelector('[data-cb-signatory-role-print]');

                if (printRole) {
                    printRole.textContent = select.selectedOptions[0]?.textContent?.trim() || '';
                }
            };

            select.addEventListener('change', sync);
            sync();
        });

        form.addEventListener('submit', (event) => {
            const documentNode = document.querySelector('[data-competitive-document]');
            const signatories = Array.from(document.querySelectorAll('[data-cb-signatory]'))
                .map((node, index) => {
                    const nameNode = node.querySelector('[data-cb-signatory-name]');
                    const roleNode = node.querySelector('[data-cb-signatory-role]');
                    const supplierNode = node.querySelector('[data-cb-supplier-name]') || document.querySelector('[data-cb-supplier-name]');

                    return {
                        slot: node.dataset.cbSlot || `signatory_${index + 1}`,
                        label: node.dataset.cbLabel || 'Signatory',
                        name: textOf(nameNode),
                        role_key: roleNode?.value || '',
                        role_label: roleNode?.selectedOptions[0]?.textContent?.trim() || '',
                        supplier_name: textOf(supplierNode),
                    };
                })
                .filter((signatory) => signatory.role_key && (signatory.name || signatory.supplier_name));

            if (!documentNode || signatories.length === 0) {
                event.preventDefault();
                window.alert('Add at least one signatory name and role before forwarding.');
                return;
            }

            const clone = documentNode.cloneNode(true);

            clone.querySelectorAll('[contenteditable]').forEach((node) => {
                node.removeAttribute('contenteditable');
            });

            clone.querySelectorAll('.no-print').forEach((node) => {
                node.remove();
            });

            clone.querySelectorAll('[data-cb-signatory-role]').forEach((node) => {
                node.remove();
            });

            clone.querySelectorAll('[data-cb-signatory-role-print]').forEach((node) => {
                node.style.display = 'block';
            });

            clone.querySelectorAll('[data-cb-signatory], [data-cb-signatory-name], [data-cb-supplier-name]').forEach((node) => {
                Array.from(node.attributes).forEach((attribute) => {
                    if (attribute.name.startsWith('data-cb-')) {
                        node.removeAttribute(attribute.name);
                    }
                });
            });

            const styles = Array.from(document.querySelectorAll('style'))
                .map((style) => style.outerHTML)
                .join('\n');

            form.querySelector('[data-cb-signatories]').value = JSON.stringify(signatories);
            form.querySelector('[data-cb-document-html]').value = `${styles}\n${clone.outerHTML}`;
        });
    })();
</script>
