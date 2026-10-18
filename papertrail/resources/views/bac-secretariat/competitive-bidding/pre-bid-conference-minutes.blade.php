@extends('layouts.dashboard')

@section('title', 'Pre-Bid Conference Minutes | PaperTrail')

@section('content')
    <style>
        .pre-bid-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
        }
        .pre-bid-toolbar h1 { margin: 0; color: #0b2341; font-size: 1.1rem; }
        .pre-bid-toolbar-actions { display: flex; flex-wrap: wrap; gap: 8px; }
        .pre-bid-toolbar button {
            min-height: 38px;
            padding: 0 14px;
            border: 1px solid #0b2341;
            border-radius: 6px;
            background: #0b2341;
            color: #fff;
            font: 700 0.85rem Figtree, Arial, sans-serif;
            cursor: pointer;
        }
        .pre-bid-toolbar button:first-child { background: #fff; color: #0b2341; }
        .pre-bid-pages {
            display: grid;
            justify-content: center;
            gap: 18px;
            overflow-x: auto;
            padding: 10px 10px 32px;
        }
        .pre-bid-page {
            width: 210mm;
            min-height: 297mm;
            padding: 25.4mm 19.05mm;
            border: 1px solid #ccd5e1;
            background: #fff;
            box-shadow: 0 10px 28px rgba(11, 35, 65, 0.09);
            box-sizing: border-box;
            color: #111;
            font: 10pt/1.25 'Times New Roman', Times, serif;
        }
        .pre-bid-page * { box-sizing: border-box; color: #111; font-family: 'Times New Roman', Times, serif; }
        .pre-bid-letterhead { margin: 0 0 20px; text-align: center; font-size: 9pt; line-height: 1.15; }
        .pre-bid-letterhead strong { display: block; margin-top: 4px; }
        .pre-bid-title { margin: 0 0 8px; font-size: 10pt; font-weight: 700; text-transform: uppercase; }
        .pre-bid-meta { display: grid; grid-template-columns: 52px minmax(0, 1fr); gap: 1px 6px; margin-bottom: 15px; }
        .pre-bid-meta dt { font-weight: 700; }
        .pre-bid-meta dd { margin: 0; }
        .pre-bid-section { margin-top: 13px; }
        .pre-bid-section h2 { margin: 0 0 6px; font-size: 10pt; font-weight: 700; }
        .pre-bid-section p { margin: 0 0 9px; text-align: justify; }
        .pre-bid-attendance { margin: 3px 0 8px 18px; padding-left: 12px; }
        .pre-bid-attendance li { margin: 1px 0; }
        .pre-bid-project { margin: 9px 0 0 18px; }
        .pre-bid-project p { margin: 0; }
        .pre-bid-signatures { margin-top: 22px; }
        .pre-bid-signatures > p { margin: 0 0 12px; font-weight: 700; }
        .pre-bid-signature-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px 10px;
            margin: 17px 0 20px;
        }
        .pre-bid-signature { min-width: 0; break-inside: avoid; }
        .pre-bid-signature strong { display: block; min-height: 15px; font-size: 9pt; text-transform: uppercase; }
        .pre-bid-signature span { font-size: 8pt; }
        .pre-bid-member-account { display: block; margin-top: 8px; font: 8pt Arial, sans-serif; color: #38506d; }
        .pre-bid-member-account select { display: block; width: 100%; margin-top: 3px; padding: 4px; font: 8pt Arial, sans-serif; }
        .pre-bid-page [contenteditable="true"] { min-width: 28px; min-height: 12px; outline: none; }
        .pre-bid-page [contenteditable="true"]:focus { background: #eff6ff; box-shadow: inset 0 0 0 1px #2563eb; }
        @media print {
            @page { size: A4 portrait; margin: 0; }
            .pre-bid-toolbar, .no-print { display: none !important; }
            .pre-bid-pages { display: block; padding: 0; overflow: visible; }
            .pre-bid-page { width: 210mm; min-height: 297mm; margin: 0; border: 0; box-shadow: none; break-after: page; }
            .pre-bid-page:last-child { break-after: auto; }
        }
    </style>

    <div class="pre-bid-toolbar no-print">
        <h1>Minutes of the Pre-Bid Conference</h1>
        <div class="pre-bid-toolbar-actions">
            <button type="button" onclick="window.print()">Print</button>
            <button type="button" data-pre-bid-save-word title="Download an editable Word file">Save Word</button>
        </div>
    </div>

    @include('bac-secretariat.competitive-bidding.partials.signature-routing-tools', [
        'templateKey' => 'pre_bid_conference_minutes',
        'documentTitle' => 'Minutes of the Pre-Bid Conference',
    ])

    <div class="pre-bid-pages" data-competitive-document>
        <section class="pre-bid-page">
            <header class="pre-bid-letterhead">
                Republic of the Philippines<br>
                Province of Southern Leyte<br>
                Municipality of Tomas Oppus<br>
                -o0o-
                <strong>BIDS AND AWARDS COMMITTEE (BAC)</strong>
            </header>

            <h2 class="pre-bid-title">Minutes of the Pre-Bid Conference</h2>
            <dl class="pre-bid-meta">
                <dt>Date:</dt><dd contenteditable="true">April 2, 2026</dd>
                <dt>Time:</dt><dd contenteditable="true">10:00 AM</dd>
                <dt>Location:</dt><dd contenteditable="true">Bids and Awards Committee (BAC) Office, 2nd floor, Legislative Building, LGU Tomas Oppus, Southern Leyte</dd>
            </dl>

            <section class="pre-bid-section">
                <h2>I. ATTENDANCE</h2>
                <strong>Present:</strong>
                <ul class="pre-bid-attendance" contenteditable="true">
                    <li>EDMAR T. TAMBIS - BAC Chairperson</li>
                    <li>Engr. ROEL J. BANO - BAC Vice Chairperson</li>
                    <li>PERPETUA D. SALAN - BAC Member</li>
                    <li>ROGELIO A. LAYO - BAC Member</li>
                    <li>JESSA G. RESUS - BAC Member</li>
                    <li>ENGR. DARLIA Y. LIGTAS - BAC TWG</li>
                    <li>RICHIE D. SALAN - BAC TWG</li>
                    <li>RICHIE D. SALAN - BAC TWG</li>
                    <li>MEAGAN C. MATUTES - BAC Secretariat</li>
                    <li>CHARLENE B. ATON - BAC Secretariat</li>
                    <li>DARIUS ADRIAN B. MALABAD - BAC Secretariat</li>
                </ul>
                <p>Prospective Bidders:<br><span contenteditable="true">None present.</span></p>
            </section>

            <section class="pre-bid-section">
                <h2>II. CALL TO ORDER</h2>
                <p contenteditable="true">The Pre-Bid Conference was called to order at exactly 10:00 AM by the BAC Chairperson, Mr. Edmar T. Tambis. Upon the BAC Secretariat's declaration of a quorum, the Chairperson formally opened the meeting.</p>
            </section>

            <section class="pre-bid-section">
                <h2>III. BUSINESS MATTERS</h2>
                <p contenteditable="true">The Chairperson introduced the agenda for the Pre-Bid Conference, which covers the public bidding for the following infrastructure projects:</p>
                <div class="pre-bid-project" contenteditable="true">
                    <p>Project 1:<br>Name of Project: PROCUREMENT OF MEDICINES<br>Approved Budget for the Contract (ABC): Php 1,999,958.50</p>
                    <p>Project 2:<br>Name of Project: Procurement of Contents and Outdoor Playground Facilities for Child Development Center (Bulilit Center)<br>Approved Budget for the Contract (ABC): Php 1,120,000.00</p>
                </div>
                <p contenteditable="true">The BAC, together with the Technical Working Group (TWG), briefly discussed the technical specifications, scope of work, and the legal and financial requirements for the aforementioned projects.</p>
            </section>
        </section>

        <section class="pre-bid-page">
            <section class="pre-bid-section">
                <p contenteditable="true">The BAC noted that no prospective bidders attended the Pre-Bid Conference. Consequently, no clarifications or questions regarding the bidding documents, technical specifications, or terms of reference were raised.</p>
                <p contenteditable="true">The Chairperson reminded the committee that any supplemental/bid bulletins may still be issued if amendments or further clarifications are needed prior to the deadline for the submission and opening of bids.</p>
            </section>

            <section class="pre-bid-section">
                <h2>IV. ADJOURNMENT</h2>
                <p contenteditable="true">There being no other matters to be discussed and no prospective bidders present to raise inquiries, the Pre-Bid Conference was formally adjourned at 11:15 am.</p>
            </section>

            <div class="pre-bid-signatures">
                <p>Prepared by:</p>
                <div class="pre-bid-signature" data-cb-signatory data-cb-slot="prepared_by" data-cb-label="Prepared by">
                    <strong contenteditable="true" data-cb-signatory-name>{{ auth()->user()?->name }}</strong>
                    @include('bac-secretariat.competitive-bidding.partials.signatory-role-select', ['selected' => 'bac_secretariat'])
                </div>

                <p style="margin-top: 22px">Attested by:</p>
                <div class="pre-bid-signature-grid">
                    @php
                        $memberNameFallbacks = [
                            'BACMEM-001' => 'Perpetua D. Salan',
                            'BACMEM-002' => 'Rogelio A. Layo',
                            'BACMEM-003' => 'Jessa G. Resus',
                        ];
                    @endphp
                    @foreach ([
                        ['chairperson', 'EDMAR T. TAMBIS', 'bac_chairperson', null],
                        ['vice_chairperson', 'ENGR. ROEL J. BANO', 'bac_vice_chairperson', null],
                        ['member_1', 'JESSA G. RESUS', 'bac_member', 'BACMEM-003'],
                        ['member_2', 'ROGELIO A. LAYO', 'bac_member', 'BACMEM-002'],
                        ['member_3', 'PERPETUA D. SALAN', 'bac_member', 'BACMEM-001'],
                    ] as [$slot, $name, $role, $memberUserId])
                        <div class="pre-bid-signature" data-cb-signatory data-cb-slot="{{ $slot }}" data-cb-label="Attested by">
                            <strong contenteditable="true" data-cb-signatory-name>{{ $name }}</strong>
                            @if ($role === 'bac_member')
                                <span class="no-print">BAC Member</span>
                                <span class="cb-signatory-role-print" data-cb-signatory-role-print>BAC Member</span>
                                <select data-cb-signatory-role class="no-print" style="display: none" aria-label="Signatory role">
                                    <option value="bac_member" selected>BAC Member</option>
                                </select>
                                <label class="pre-bid-member-account no-print">
                                    Signer account
                                    <select data-cb-recipient-user-id aria-label="Signer account for {{ $name }}" required>
                                        <option value="">Select account</option>
                                        @foreach ($bacMembers as $bacMember)
                                            @php
                                                $signerName = $bacMember->name === 'BAC Member'
                                                    ? ($memberNameFallbacks[$bacMember->user_id] ?? $bacMember->name)
                                                    : $bacMember->name;
                                            @endphp
                                            <option value="{{ $bacMember->id }}" data-signer-name="{{ $signerName }}" @selected($memberUserId === $bacMember->user_id)>
                                                {{ $bacMember->user_id }} - {{ $signerName }}
                                            </option>
                                        @endforeach
                                    </select>
                                </label>
                            @else
                                @include('bac-secretariat.competitive-bidding.partials.signatory-role-select', ['selected' => $role])
                            @endif
                        </div>
                    @endforeach
                </div>

                <p>Approved by:</p>
                <div class="pre-bid-signature" data-cb-signatory data-cb-slot="mayor" data-cb-label="Approved by">
                    <strong contenteditable="true" data-cb-signatory-name>HON. ROD IVAN CUARES PANO</strong>
                    @include('bac-secretariat.competitive-bidding.partials.signatory-role-select', ['selected' => 'hope'])
                </div>
            </div>
        </section>
    </div>
    <script>
        document.querySelector('[data-pre-bid-save-word]')?.addEventListener('click', () => {
            const documentNode = document.querySelector('[data-competitive-document]')?.cloneNode(true);
            if (!documentNode) return;

            documentNode.querySelectorAll('[contenteditable]').forEach((node) => node.removeAttribute('contenteditable'));
            documentNode.querySelectorAll('.no-print, [data-cb-signatory-role]').forEach((node) => node.remove());

            const styles = Array.from(document.querySelectorAll('style')).map((style) => style.outerHTML).join('\n');
            const wordStyles = `<style>
                @page { size: A4 portrait; margin: 25.4mm 19.05mm; }
                body { margin: 0; color: #111; font-family: 'Times New Roman', Times, serif; }
                [data-competitive-document], [data-competitive-document] > section {
                    display: block !important; width: auto !important; max-width: none !important;
                    min-height: 0 !important; margin: 0 !important; padding: 0 !important;
                    border: 0 !important; box-shadow: none !important; overflow: visible !important;
                }
                .cb-signatory-role-print { display: block !important; }
            </style>`;
            const html = `<!doctype html><html><head><meta charset="utf-8"><title>Minutes of the Pre-Bid Conference</title>${styles}${wordStyles}</head><body>${documentNode.outerHTML}</body></html>`;
            const url = URL.createObjectURL(new Blob(['\ufeff', html], { type: 'application/msword;charset=utf-8' }));
            const link = document.createElement('a');
            link.href = url;
            link.download = 'pre-bid-conference-minutes.doc';
            document.body.appendChild(link);
            link.click();
            link.remove();
            setTimeout(() => URL.revokeObjectURL(url), 60000);
        });

        document.querySelectorAll('.pre-bid-signature [data-cb-recipient-user-id]').forEach((select) => {
            const nameNode = select.closest('[data-cb-signatory]')?.querySelector('[data-cb-signatory-name]');
            const syncName = () => {
                const signerName = select.selectedOptions[0]?.dataset.signerName;
                if (nameNode && signerName) {
                    nameNode.textContent = signerName;
                }
            };

            select.addEventListener('change', syncName);
            syncName();
        });
    </script>
@endsection
