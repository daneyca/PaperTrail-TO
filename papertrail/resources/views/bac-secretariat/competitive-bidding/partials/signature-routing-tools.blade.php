@php
    $templateKey = $templateKey ?? 'competitive_bidding_document';
    $documentTitle = $documentTitle ?? 'Competitive Bidding Document';
    $pageInsets = [
        'pre_bid_conference_minutes' => '25.4mm 19.05mm',
        'technical_envelope_checklist' => '19mm 18mm 12mm',
        'financial_envelope_checklist' => '19mm 18mm 19mm 22mm',
        'bac_resolution_on_post_qualification' => '20mm 27mm 18mm',
        'bid_evaluation_report' => '18mm 22mm 16mm',
        'contract_agreement' => '20mm 25mm 18mm',
        'post_qualification_evaluation_summary' => '17mm 22mm 16mm',
        'post_qualification_report_twg' => '18mm 24mm 16mm',
        'notice_of_award' => '22mm 27mm 20mm',
        'notice_of_post_qualification' => '23mm 27mm 20mm',
        'notice_to_proceed' => '24mm 30mm 20mm',
    ];
    $pageInset = $pageInsets[$templateKey] ?? '20mm';
    $biddingRecords = \App\Models\CompetitiveBiddingRecord::query()
        ->where('status', \App\Models\CompetitiveBiddingRecord::STATUS_OPEN)
        ->orderByDesc('created_at')
        ->get(['reference_number', 'project_title']);
@endphp

<style>
    .cb-routing-actions {
        display: flex;
        justify-content: flex-end;
        margin-bottom: 16px;
    }

    .cb-routing-dialog {
        width: min(420px, calc(100vw - 32px));
        max-height: calc(100vh - 32px);
        overflow-y: auto;
        border: 1px solid #d8dee8;
        border-radius: 8px;
        padding: 22px;
        color: #0b2341;
        background: #fff;
        box-shadow: 0 24px 60px rgba(11, 35, 65, 0.22);
    }

    .cb-routing-dialog::backdrop { background: rgba(11, 35, 65, 0.42); }
    .cb-routing-dialog h2 { margin: 0 0 6px; font: 800 1.15rem/1.25 Figtree, Arial, sans-serif; }
    .cb-routing-dialog p { margin: 0 0 18px; color: #52647d; font: 400 0.86rem/1.45 Figtree, Arial, sans-serif; }
    .cb-routing-panel { display: grid; gap: 18px; }
    .cb-routing-dialog-actions { display: flex; justify-content: flex-end; gap: 8px; }
    .cb-routing-panel button:disabled { opacity: 0.55; cursor: not-allowed; }

    .cb-routing-panel label {
        display: grid;
        gap: 5px;
        min-width: 0;
        color: #52647d;
        font: 800 0.72rem/1.2 Figtree, Arial, sans-serif;
        text-transform: uppercase;
    }

    .cb-routing-panel input,
    .cb-routing-panel select {
        width: 100%;
        min-height: 38px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 0 11px;
        color: #0b2341;
        font: 700 0.86rem/1.2 Figtree, Arial, sans-serif;
        text-transform: none;
    }

    .cb-routing-actions button,
    .cb-routing-panel button {
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

    .cb-routing-panel button[type="button"] {
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
        .cb-routing-actions,
        .cb-routing-dialog,
        .cb-routing-alert,
        .cb-signatory-role-select {
            display: none !important;
        }

        .cb-signatory-role-print {
            display: block !important;
        }
    }

    [data-competitive-document] {
        display: block !important;
        width: 210mm !important;
        max-width: 100% !important;
        min-height: 297mm !important;
        margin: 0 auto !important;
        padding: {{ $pageInset }} !important;
        overflow: visible !important;
        border: 1px solid #cbd5e1 !important;
        background: #fff !important;
        box-shadow: 0 12px 32px rgba(15, 23, 42, 0.1) !important;
        box-sizing: border-box !important;
    }

    [data-competitive-document] > section {
        display: contents !important;
    }

    @media print {
        @page {
            size: A4 portrait;
            margin: {{ $pageInset }};
        }

        [data-competitive-document] {
            width: auto !important;
            max-width: none !important;
            min-height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
            border: 0 !important;
            box-shadow: none !important;
            overflow: visible !important;
        }

        [data-competitive-document] > section {
            display: contents !important;
            break-after: auto !important;
            page-break-after: auto !important;
        }

        [data-competitive-document] h1,
        [data-competitive-document] h2,
        [data-competitive-document] h3 {
            break-after: avoid;
        }

        [data-competitive-document] thead {
            display: table-header-group;
        }

        [data-competitive-document] tr,
        [data-competitive-document] [data-cb-signatory] {
            break-inside: avoid;
        }
    }
</style>

@if (session('status'))
    <div class="cb-routing-alert no-print">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="cb-routing-alert is-error no-print">{{ $errors->first() }}</div>
@endif

<div class="cb-routing-actions no-print">
    <button type="button" data-cb-routing-open="{{ $templateKey }}">Submit for Signatures</button>
</div>

<dialog class="cb-routing-dialog no-print" data-cb-routing-dialog="{{ $templateKey }}" aria-label="Submit for signatures">
    <h2>Submit for Signatures</h2>
    <p>Choose the bidding file where the signed document belongs.</p>
    <form
        method="POST"
        action="{{ route('bac-secretariat.competitive-bidding.signature-documents.store') }}"
        class="cb-routing-panel"
        data-cb-routing-form="{{ $templateKey }}"
    >
        @csrf
        <input type="hidden" name="template_key" value="{{ $templateKey }}">
        <input type="hidden" name="document_title" value="{{ $documentTitle }}">
        <input type="hidden" name="document_html" data-cb-document-html>
        <input type="hidden" name="signatories" data-cb-signatories>
        @if (in_array($templateKey, ['technical_envelope_checklist', 'financial_envelope_checklist'], true))
            <input type="hidden" name="checklist_result" value="complying">
        @endif

        <label>
            Bidding File
            <select name="tracking_number" required>
                <option value="">Select reference</option>
                @foreach ($biddingRecords as $biddingRecord)
                    <option value="{{ $biddingRecord->reference_number }}" @selected(old('tracking_number') === $biddingRecord->reference_number)>
                        {{ $biddingRecord->reference_number }} - {{ $biddingRecord->project_title }}
                    </option>
                @endforeach
            </select>
        </label>

        @if ($biddingRecords->isEmpty())
            <p>No open bidding file is available yet. Create one before submitting for signatures.</p>
        @endif

        <div class="cb-routing-dialog-actions">
            <button type="button" data-cb-routing-close>Cancel</button>
            <button type="submit" @disabled($biddingRecords->isEmpty())>Submit</button>
        </div>
    </form>
</dialog>

<script>
    (() => {
        const form = document.querySelector('[data-cb-routing-form="{{ $templateKey }}"]');

        if (!form) {
            return;
        }

        const dialog = document.querySelector('[data-cb-routing-dialog="{{ $templateKey }}"]');
        document.querySelector('[data-cb-routing-open="{{ $templateKey }}"]')?.addEventListener('click', () => dialog.showModal());
        dialog?.querySelector('[data-cb-routing-close]')?.addEventListener('click', () => dialog.close());

        @if ($errors->any())
            dialog?.showModal();
        @endif

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
                .filter((node) => node.closest('[data-cb-signatory-entry]')?.querySelector('[data-cb-participating]')?.checked !== false)
                .map((node, index) => {
                    const nameNode = node.querySelector('[data-cb-signatory-name]');
                    const roleNode = node.querySelector('[data-cb-signatory-role]');
                    const recipientNode = node.querySelector('[data-cb-recipient-user-id]')
                        || node.closest('[data-cb-signatory-entry]')?.querySelector('[data-cb-recipient-user-id]');
                    const supplierNode = node.querySelector('[data-cb-supplier-name]') || document.querySelector('[data-cb-supplier-name]');

                    return {
                        slot: node.dataset.cbSlot || `signatory_${index + 1}`,
                        label: node.dataset.cbLabel || 'Signatory',
                        name: textOf(nameNode),
                        role_key: roleNode?.value || '',
                        role_label: roleNode?.selectedOptions[0]?.textContent?.trim() || '',
                        recipient_user_id: recipientNode?.value || '',
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
            clone.querySelectorAll('[data-cb-signatory-entry].is-inactive').forEach((node) => node.remove());
            clone.querySelectorAll('input[type="radio"]').forEach((node) => {
                const selected = documentNode.querySelector(`input[name="${node.name}"][value="${node.value}"]`);
                node.toggleAttribute('checked', selected?.checked === true);
            });

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
