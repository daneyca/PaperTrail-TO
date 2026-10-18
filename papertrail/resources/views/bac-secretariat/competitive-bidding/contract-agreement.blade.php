@extends('layouts.dashboard')

@section('title', 'Contract Agreement | PaperTrail')

@section('content')
    <style>
        .contract-agreement-toolbar {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            margin-bottom: 16px;
            padding: 14px 16px;
            border: 1px solid #d8dee8;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 10px 28px rgba(11, 35, 65, 0.06);
        }

        .contract-agreement-toolbar > div {
            display: grid;
            gap: 2px;
            margin-right: auto;
        }

        .contract-agreement-toolbar strong {
            color: #0b2341;
            font-size: 0.98rem;
        }

        .contract-agreement-toolbar a,
        .contract-agreement-toolbar button {
            display: inline-flex;
            min-height: 38px;
            align-items: center;
            justify-content: center;
            padding: 0 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #fff;
            color: #0b2341;
            font: 800 0.84rem/1 Figtree, Arial, sans-serif;
            text-decoration: none;
            cursor: pointer;
        }

        .contract-agreement-toolbar button {
            border-color: #0b2341;
            background: #0b2341;
            color: #fff;
        }

        .contract-agreement-wrap {
            display: grid;
            justify-content: center;
            gap: 18px;
            width: 100%;
            overflow: auto;
            padding: 18px 12px 42px;
            box-sizing: border-box;
        }

        .contract-agreement-page {
            width: 210mm !important;
            min-height: 297mm !important;
            padding: 20mm 25mm 18mm !important;
            border: 1px solid #c8c8c8;
            background: #fff;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.14);
            color: #000;
            font-family: Arial, Helvetica, sans-serif !important;
            font-size: 7.25pt !important;
            line-height: 1.26 !important;
            box-sizing: border-box;
        }

        .contract-agreement-page * {
            box-sizing: border-box;
            font-family: Arial, Helvetica, sans-serif !important;
            color: #000;
        }

        .contract-agreement-page [contenteditable="true"] {
            min-height: 10px;
            outline: none;
        }

        .contract-agreement-page [contenteditable="true"]:focus {
            background: #eff6ff;
            box-shadow: inset 0 0 0 1px #2563eb;
        }

        .contract-agreement-header {
            display: grid;
            grid-template-columns: 45px minmax(0, 1fr);
            gap: 9px;
            align-items: center;
            width: 282px;
            margin: 0 auto 9px;
        }

        .contract-agreement-logo {
            width: 42px;
            height: 42px;
            object-fit: contain;
        }

        .contract-agreement-heading {
            text-align: center;
            font-size: 6.6pt !important;
            line-height: 1.08 !important;
        }

        .contract-agreement-heading strong {
            display: block;
            font-size: 7.4pt !important;
            text-transform: uppercase;
        }

        .contract-agreement-title {
            display: grid;
            gap: 4px;
            margin-bottom: 13px;
            text-align: center;
        }

        .contract-agreement-title h1 {
            margin: 0;
            font-size: 10pt !important;
            font-weight: 700;
            text-transform: uppercase;
        }

        .contract-agreement-title p {
            margin: 0;
            font-size: 6.7pt !important;
            font-style: italic;
            font-weight: 700;
        }

        .contract-agreement-kamm {
            margin: 0 0 12px;
            text-align: center;
            font-size: 8pt !important;
            font-weight: 700;
            text-transform: uppercase;
        }

        .contract-agreement-body {
            display: grid;
            gap: 9px;
        }

        .contract-agreement-body p {
            margin: 0;
            text-align: justify;
            text-indent: 27px;
        }

        .contract-agreement-body p.no-indent {
            text-indent: 0;
        }

        .contract-agreement-section-title {
            margin-top: 4px;
            font-size: 7.5pt !important;
            font-weight: 700;
            text-transform: uppercase;
        }

        .contract-agreement-list {
            display: grid;
            gap: 3px;
            margin: 0 0 0 42px;
            padding: 0;
        }

        .contract-agreement-list li {
            padding-left: 5px;
            text-align: justify;
        }

        .contract-agreement-signatures {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px 48px;
            margin-top: 19px;
        }

        .contract-agreement-party {
            text-align: center;
        }

        .contract-agreement-party-label {
            margin-bottom: 24px;
            font-size: 7.1pt !important;
            font-weight: 700;
            text-transform: uppercase;
        }

        .contract-agreement-party strong,
        .contract-agreement-witness strong {
            display: block;
            font-size: 7.2pt !important;
            text-decoration: underline;
            text-transform: uppercase;
        }

        .contract-agreement-party span,
        .contract-agreement-witness span {
            display: block;
            font-size: 6.9pt !important;
        }

        .contract-agreement-witnesses {
            margin-top: 10px;
        }

        .contract-agreement-witnesses > p {
            margin: 0 0 25px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .contract-agreement-witness-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 40px;
            text-align: center;
        }

        .contract-agreement-ack-title {
            margin: 44px 0 20px;
            text-align: center;
            font-weight: 700;
            text-transform: uppercase;
        }

        .contract-agreement-ack-lines {
            display: grid;
            gap: 6px;
            width: 100%;
        }

        .contract-agreement-line {
            display: grid;
            grid-template-columns: max-content minmax(105px, 1fr) max-content minmax(105px, 1fr);
            gap: 7px;
            align-items: end;
        }

        .contract-agreement-line span:nth-child(2),
        .contract-agreement-line span:nth-child(4),
        .contract-agreement-single-line {
            display: block;
            min-height: 10px;
            border-bottom: 1px solid #000;
        }

        .contract-agreement-notary {
            width: 205px;
            margin: 24px auto 26px;
            border-top: 1px solid #000;
            padding-top: 6px;
            text-align: center;
            font-size: 7pt !important;
            font-weight: 700;
            text-transform: uppercase;
        }

        .contract-agreement-doc-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 7px 86px;
            width: 84%;
            margin-top: 18px;
        }

        .contract-agreement-doc-grid div {
            display: grid;
            grid-template-columns: max-content minmax(70px, 1fr);
            gap: 6px;
            align-items: end;
        }

        .contract-agreement-doc-grid span:last-child {
            display: block;
            min-height: 10px;
            border-bottom: 1px solid #000;
        }

        @media (max-width: 980px) {
            .contract-agreement-wrap {
                justify-content: flex-start;
            }
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 0;
            }

            .contract-agreement-toolbar,
            .no-print {
                display: none !important;
            }

            .contract-agreement-wrap {
                display: block !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: visible !important;
                background: #fff !important;
            }

            .contract-agreement-page {
                width: 210mm !important;
                min-height: 297mm !important;
                margin: 0 !important;
                padding: 20mm 25mm 18mm !important;
                border: 0 !important;
                box-shadow: none !important;
                break-after: page;
            }

            .contract-agreement-page:last-child {
                break-after: auto;
            }
        }
    </style>

    <section class="contract-agreement-toolbar no-print">
        <div>
            <strong>Contract Agreement</strong>
        </div>
        <a href="{{ route('bac-secretariat.competitive-bidding.contract-agreement') }}">Back</a>
        <button type="button" onclick="window.print()">Print</button>
    </section>

    @include('bac-secretariat.competitive-bidding.partials.signature-routing-tools', [
        'templateKey' => 'contract_agreement',
        'documentTitle' => 'Contract Agreement',
    ])

    <div class="contract-agreement-wrap" data-competitive-document>
        <section class="contract-agreement-page">
            <header class="contract-agreement-header">
                <img src="{{ asset('images/logos/lgu-logo.png') }}" alt="LGU Tomas Oppus seal" class="contract-agreement-logo">
                <div class="contract-agreement-heading" contenteditable="true">
                    Republic of the Philippines<br>
                    Province of Southern Leyte<br>
                    <strong>Municipality of Tomas Oppus</strong>
                </div>
            </header>

            <div class="contract-agreement-title">
                <h1 contenteditable="true">Contract Agreement</h1>
                <p contenteditable="true">(Pursuant to Republic Act No. 12009 and its Revised Implementing Rules and Regulations)</p>
            </div>

            <div class="contract-agreement-kamm" contenteditable="true">Know All Men By These Presents:</div>

            <div class="contract-agreement-body">
                <p contenteditable="true">This Contract Agreement is entered into by and between:</p>

                <p contenteditable="true">The LOCAL GOVERNMENT UNIT OF TOMAS OPPUS, Province of Southern Leyte, represented herein by the Local Chief Executive, HON. ROD IVAN CUARES PANO, Municipal Mayor, with office address at Municipal Hall, Tomas Oppus, Southern Leyte, hereinafter referred to as the "PROCURING ENTITY";</p>

                <p class="no-indent" contenteditable="true" style="text-align: center; font-weight: 700;">- and -</p>

                <p contenteditable="true">BETH GENERAL MERCHANDISE, a corporation duly organized and existing under and by virtue of the laws of the Republic of the Philippines, with principal office address at Zone II, Sogod, Southern Leyte, represented herein by its Owner, ALANA J. PALMA, duly authorized to sign this Agreement, hereinafter referred to as the "SUPPLIER/CONTRACTOR";</p>

                <p class="no-indent" contenteditable="true" style="font-weight: 700; text-transform: uppercase;">Witnesseth:</p>

                <p contenteditable="true">WHEREAS, the PROCURING ENTITY invited Bids for the Procurement of Materials for the Improvement of Felicity Dream Square under Project Reference No. 10385493;</p>

                <p contenteditable="true">WHEREAS, the SUPPLIER/CONTRACTOR submitted a Bid in accordance with the Philippine Bidding Documents (PBDs) and the terms and conditions set forth therein;</p>

                <p contenteditable="true">WHEREAS, after evaluation by the Bids and Awards Committee (BAC), the SUPPLIER/CONTRACTOR was determined to have the Single Calculated Responsive Bid and was recommended for award;</p>

                <p contenteditable="true">WHEREAS, the PROCURING ENTITY accepted the Bid of the SUPPLIER/CONTRACTOR and issued a Notice of Award dated July 29, 2026 in the amount of ONE MILLION SEVEN HUNDRED NINE THOUSAND TWO HUNDRED NINETY-THREE PESOS ONLY (PHP 1,709,293.00), Philippine Currency;</p>

                <p contenteditable="true">NOW, THEREFORE, for and in consideration of the foregoing premises and the mutual covenants and agreements hereinafter set forth, the parties have agreed as follows:</p>

                <div class="contract-agreement-section-title" contenteditable="true">Article I - Scope of Work</div>
                <p contenteditable="true">The SUPPLIER shall supply and deliver the following items at the site location of LGU Tomas Oppus, Barangay San Isidro, Tomas Oppus, Southern Leyte, in accordance with the specifications stated in the Philippine Bidding Documents.</p>
            </div>
        </section>

        <section class="contract-agreement-page">
            <div class="contract-agreement-body">
                <div class="contract-agreement-section-title" contenteditable="true">Article II - Contract Price</div>
                <p contenteditable="true">The PROCURING ENTITY shall pay the SUPPLIER/CONTRACTOR the total amount of ONE MILLION SEVEN HUNDRED NINE THOUSAND TWO HUNDRED NINETY-THREE PESOS ONLY (PHP 1,709,293.00), Philippine Currency, inclusive of all applicable taxes and charges, for the complete supply and delivery of the goods and services described in Article I.</p>

                <div class="contract-agreement-section-title" contenteditable="true">Article III - Terms of Payment</div>
                <p contenteditable="true">Payment shall be made in full upon complete delivery and acceptance of the goods and services by the PROCURING ENTITY, subject to the submission of the following documentary requirements:</p>
                <ol class="contract-agreement-list" type="a">
                    <li contenteditable="true">Delivery Receipt duly signed by the authorized representative of the PROCURING ENTITY;</li>
                    <li contenteditable="true">Certificate of Acceptance/Inspection and Acceptance Report;</li>
                    <li contenteditable="true">Sales Invoice/Official Receipt;</li>
                    <li contenteditable="true">Other supporting documents as may be required.</li>
                </ol>

                <div class="contract-agreement-section-title" contenteditable="true">Article IV - Delivery</div>
                <p contenteditable="true">The SUPPLIER/CONTRACTOR shall complete the delivery of all goods within fifteen (15) calendar days from receipt of the Notice to Proceed, unless extended by the PROCURING ENTITY due to force majeure or other justifiable reasons.</p>

                <div class="contract-agreement-section-title" contenteditable="true">Article V - Effectivity</div>
                <p contenteditable="true">This Contract Agreement shall take effect upon its approval by the appropriate authority and shall remain in force until the complete fulfillment of all obligations of both parties, including the expiration of the warranty period.</p>

                <div class="contract-agreement-section-title" contenteditable="true">Article VI - General Provisions</div>
                <p contenteditable="true">The Philippine Bidding Documents, including the Instructions to Bidders, Bid Data Sheet, General Conditions of Contract, Special Conditions of Contract, Technical Specifications, Schedule of Requirements, and the SUPPLIER/CONTRACTOR's Bid, are hereby incorporated by reference as integral parts of this Contract Agreement.</p>

                <p contenteditable="true">Any dispute arising from this Contract Agreement shall be settled in accordance with the provisions of R.A. No. 12009 and its Revised Implementing Rules and Regulations.</p>

                <p contenteditable="true">IN WITNESS WHEREOF, the parties have hereunto set their hands this 3rd day of August 2026, at Tomas Oppus, Southern Leyte, Philippines.</p>
            </div>

            <div class="contract-agreement-signatures">
                <div class="contract-agreement-party">
                    <div class="contract-agreement-party-label" contenteditable="true">For the Procuring Entity:</div>
                    <div data-cb-signatory data-cb-slot="procuring_entity" data-cb-label="For the Procuring Entity">
                        <strong contenteditable="true" data-cb-signatory-name>Hon. Rod Ivan Cuares Pano</strong>
                        @include('bac-secretariat.competitive-bidding.partials.signatory-role-select', ['selected' => 'hope'])
                    </div>
                    <span contenteditable="true">LGU Tomas Oppus, Southern Leyte</span>
                </div>

                <div class="contract-agreement-party">
                    <div class="contract-agreement-party-label" contenteditable="true">For the Supplier/Contractor:</div>
                    <div data-cb-signatory data-cb-slot="supplier_contractor" data-cb-label="For the Supplier/Contractor">
                        <strong contenteditable="true" data-cb-signatory-name>Alana J. Palma</strong>
                        @include('bac-secretariat.competitive-bidding.partials.signatory-role-select', ['selected' => 'supplier', 'onlySupplier' => true])
                    </div>
                    <span contenteditable="true" data-cb-supplier-name>Beth General Merchandise</span>
                </div>
            </div>
        </section>

        <section class="contract-agreement-page">
            <div class="contract-agreement-witnesses">
                <p contenteditable="true">Signed in the presence of:</p>
                <div class="contract-agreement-witness-grid">
                    <div class="contract-agreement-witness" data-cb-signatory data-cb-slot="witness_1" data-cb-label="Witness">
                        <strong contenteditable="true" data-cb-signatory-name>Edmar T. Tambis</strong>
                        @include('bac-secretariat.competitive-bidding.partials.signatory-role-select', ['selected' => 'bac_chairperson'])
                    </div>
                    <div class="contract-agreement-witness" data-cb-signatory data-cb-slot="witness_2" data-cb-label="Witness">
                        <strong contenteditable="true" data-cb-signatory-name>Richie D. Salan</strong>
                        @include('bac-secretariat.competitive-bidding.partials.signatory-role-select', ['selected' => 'twg'])
                    </div>
                </div>
            </div>

            <div class="contract-agreement-ack-title" contenteditable="true">Acknowledgment</div>

            <div class="contract-agreement-body">
                <p class="no-indent" contenteditable="true">Republic of the Philippines)<br>Province of Southern Leyte) S.S.<br>Municipality of Tomas Oppus)</p>

                <p contenteditable="true">BEFORE ME, a Notary Public for and in the above jurisdiction, this ______ day of _____________ 2026, personally appeared:</p>
            </div>

            <div class="contract-agreement-ack-lines">
                <div class="contract-agreement-line">
                    <span contenteditable="true">Name</span>
                    <span contenteditable="true">HON. ROD IVAN CUARES PANO</span>
                    <span contenteditable="true">Valid ID/CTC No.</span>
                    <span contenteditable="true">14284284</span>
                </div>
                <div class="contract-agreement-line">
                    <span contenteditable="true">Name</span>
                    <span contenteditable="true">ALANA J. PALMA</span>
                    <span contenteditable="true">Valid ID/CTC No.</span>
                    <span contenteditable="true">01-05-2026</span>
                </div>
            </div>

            <div class="contract-agreement-body" style="margin-top: 10px;">
                <p contenteditable="true">known to me and to me known to be the same persons who executed the foregoing Contract Agreement, and they acknowledged to me that the same is their free and voluntary act and deed as well as that of the entities they represent.</p>

                <p contenteditable="true">This Contract Agreement, including this acknowledgment, has been signed by the parties and their witnesses on each and every page thereof.</p>

                <p contenteditable="true">WITNESS MY HAND AND SEAL on the date and place first above written.</p>
            </div>

            <div class="contract-agreement-notary" contenteditable="true">Notary Public</div>

            <div contenteditable="true">Until 31 December 20___</div>

            <div class="contract-agreement-doc-grid">
                <div><span contenteditable="true">PTR No.</span><span contenteditable="true"></span></div>
                <div><span contenteditable="true">Issued at</span><span contenteditable="true"></span></div>
                <div><span contenteditable="true">Doc. No.</span><span contenteditable="true"></span></div>
                <div><span contenteditable="true">Issued on</span><span contenteditable="true"></span></div>
                <div><span contenteditable="true">Page No.</span><span contenteditable="true"></span></div>
                <div><span contenteditable="true">TIN No.</span><span contenteditable="true"></span></div>
                <div><span contenteditable="true">Book No.</span><span contenteditable="true"></span></div>
                <div></div>
                <div><span contenteditable="true">Series of</span><span contenteditable="true"></span></div>
            </div>
        </section>
    </div>
@endsection
