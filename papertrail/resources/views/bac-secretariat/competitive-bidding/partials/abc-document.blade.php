<style>
    .abc-snapshot { width:390mm; min-height:265mm; padding:9mm 8mm; box-sizing:border-box; background:#fff; color:#111; font:7pt/1.2 Arial,sans-serif; }
    .abc-snapshot * { box-sizing:border-box; }
    .abc-snapshot table { width:100%; border-collapse:collapse; table-layout:fixed; }
    .abc-snapshot col:nth-child(1) { width:4%; }
    .abc-snapshot col:nth-child(2) { width:20%; }
    .abc-snapshot col:nth-child(3),.abc-snapshot col:nth-child(4) { width:5%; }
    .abc-snapshot col:nth-child(5) { width:9%; }
    .abc-snapshot col:nth-child(n+6):nth-child(-n+9) { width:5%; }
    .abc-snapshot col:nth-child(10) { width:7%; }
    .abc-snapshot col:nth-child(11) { width:6%; }
    .abc-snapshot col:nth-child(12),.abc-snapshot col:nth-child(13) { width:7%; }
    .abc-snapshot col:nth-child(14) { width:10%; }
    .abc-snapshot th,.abc-snapshot td { border:1px solid #333; padding:3px; vertical-align:middle; white-space:pre-wrap; overflow-wrap:anywhere; }
    .abc-snapshot th { text-align:center; }
    .abc-snapshot .heading th { border:0; padding:0 0 6mm; }
    .abc-snapshot .heading p { margin:1px; font-weight:400; }
    .abc-snapshot .heading h1 { margin:4mm 0 3mm; font-size:12pt; }
    .abc-snapshot .heading h2 { margin:2px; font-size:10pt; }
    .abc-snapshot .numbers th { font-weight:400; }
    .abc-snapshot .amount { text-align:right; }
    .abc-snapshot tfoot td { font-weight:700; }
    .abc-snapshot .signatures { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:9mm; margin-top:18mm; }
    .abc-snapshot .signatures section { min-width:0; text-align:center; }
    .abc-snapshot .signatures strong { display:block; min-height:13mm; text-align:left; font-size:8pt; }
    .abc-snapshot .signature-line { display:block; height:11mm; }
    .abc-snapshot .signature-line img { display:block; max-width:100%; max-height:11mm; margin:auto; }
    .abc-snapshot .printed-name { border-bottom:1px solid #333; font-weight:700; text-transform:uppercase; }
    @media print {
        @page { size:A4 landscape; margin:8mm; }
        html,body { width:auto !important; min-width:0 !important; }
        .abc-snapshot,.abc-snapshot * { visibility:visible !important; }
        .abc-snapshot { width:100% !important; min-height:0 !important; padding:0 !important; font-size:6.5pt !important; }
        .abc-snapshot thead { display:table-header-group; }
        .abc-snapshot tr,.abc-snapshot .signatures { break-inside:avoid; }
    }
</style>
<article class="abc-snapshot">
    <table>
        <colgroup>@for ($i=0; $i<14; $i++)<col>@endfor</colgroup>
        <thead>
            <tr class="heading"><th colspan="14"><p>Republic of the Philippines</p><p>Province of Southern Leyte</p><p><strong>MUNICIPALITY OF TOMAS OPPUS</strong></p><h1>APPROVED BUDGET FOR THE CONTRACT</h1><h2>{{ $data['project'] }}</h2><p>{{ $data['location'] }}</p></th></tr>
            <tr><th rowspan="2">ITEM<br>NO.</th><th rowspan="2">DESCRIPTION</th><th rowspan="2">QUANTITY</th><th rowspan="2">UNIT</th><th rowspan="2">ESTIMATED<br>DIRECT COST</th><th colspan="3">MARK-UPS<br>IN PERCENT</th><th colspan="2">TOTAL MARK-UP</th><th rowspan="2">VAT</th><th rowspan="2">TOTAL<br>INDIRECT</th><th rowspan="2">UNIT COST</th><th rowspan="2">TOTAL COST</th></tr>
            <tr><th>OCM</th><th>PROFIT</th><th>MOB. /<br>DEMOB.</th><th>%</th><th>VALUE</th></tr>
            <tr class="numbers">@for ($i=1; $i<=14; $i++)<th>({{ $i }})</th>@endfor</tr>
        </thead>
        <tbody>@foreach ($data['items'] as $item)<tr>
            @foreach (\App\Services\CompetitiveBiddingAbcService::FIELDS as $field)
                <td @class(['amount' => in_array($field, ['direct_cost','markup_value','vat','total_indirect','unit_cost','total_cost'])])>{{ in_array($field, ['quantity','direct_cost','ocm_percent','profit_percent','mob_demob_percent','markup_percent','markup_value','vat','total_indirect','unit_cost','total_cost']) && isset($item[$field]) && $item[$field] !== '' ? number_format((float) $item[$field], 2) : ($item[$field] ?? '') }}</td>
            @endforeach
        </tr>@endforeach</tbody>
        <tfoot><tr><td colspan="13">TOTAL</td><td class="amount">{{ number_format(array_sum(array_column($data['items'], 'total_cost')), 2) }}</td></tr></tfoot>
    </table>
    <div class="signatures">
        @foreach (\App\Services\CompetitiveBiddingAbcService::SLOTS as $slot => $label)
            <section><strong>{{ $label }}:</strong><span class="signature-line" data-abc-signature-slot="{{ $slot }}"></span><div class="printed-name">{{ $data[$slot.'_name'] }}</div><div>{{ $data[$slot.'_title'] ?? '' }}</div></section>
        @endforeach
    </div>
</article>
