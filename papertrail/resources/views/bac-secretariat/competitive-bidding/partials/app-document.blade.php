<style>
    .cb-app-snapshot { width:390mm; min-height:260mm; padding:10mm 8mm; box-sizing:border-box; background:#fff; color:#111; font:7pt/1.2 Arial,sans-serif; }
    .cb-app-snapshot * { box-sizing:border-box; }
    .cb-app-snapshot header { text-align:center; margin-bottom:8mm; }
    .cb-app-snapshot header p { margin:1px; }
    .cb-app-snapshot h1 { margin:3px 0; font-size:12pt; }
    .cb-app-snapshot table { width:100%; border-collapse:collapse; table-layout:fixed; }
    .cb-app-snapshot col:nth-child(1) { width:5%; }
    .cb-app-snapshot col:nth-child(2) { width:14%; }
    .cb-app-snapshot col:nth-child(3),.cb-app-snapshot col:nth-child(4) { width:8%; }
    .cb-app-snapshot col:nth-child(n+5):nth-child(-n+8) { width:5%; }
    .cb-app-snapshot col:nth-child(9) { width:7%; }
    .cb-app-snapshot col:nth-child(n+10):nth-child(-n+13) { width:6%; }
    .cb-app-snapshot col:nth-child(14) { width:14%; }
    .cb-app-snapshot th,.cb-app-snapshot td { border:1px solid #333; padding:4px; vertical-align:middle; white-space:pre-wrap; overflow-wrap:anywhere; }
    .cb-app-snapshot th { text-align:center; }
    .cb-app-snapshot .amount { text-align:right; }
    .cb-app-snapshot tfoot td { font-weight:700; }
    .cb-app-snapshot .signatures { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:8mm; margin-top:20mm; }
    .cb-app-snapshot .signatures section { min-width:0; text-align:center; }
    .cb-app-snapshot .signatures strong { display:block; min-height:12mm; text-align:left; font-size:8pt; }
    .cb-app-snapshot .signature-line { display:block; height:12mm; }
    .cb-app-snapshot .signature-line img { display:block; max-width:100%; max-height:12mm; margin:auto; }
    .cb-app-snapshot .printed-name { border-bottom:1px solid #333; font-weight:700; text-transform:uppercase; }
    @media print {
        @page { size:A4 landscape; margin:8mm; }
        html,body { width:auto !important; min-width:0 !important; }
        .cb-app-snapshot,.cb-app-snapshot * { visibility:visible !important; }
        .cb-app-snapshot { width:100% !important; min-height:0 !important; padding:0 !important; font-size:6.4pt !important; }
        .cb-app-snapshot thead { display:table-header-group; }
        .cb-app-snapshot tr,.cb-app-snapshot .signatures { break-inside:avoid; }
    }
</style>
<article class="cb-app-snapshot">
    <header><p>Republic of the Philippines</p><p>Province of Southern Leyte</p><p><strong>MUNICIPALITY OF TOMAS OPPUS</strong></p><h1>Annual Procurement Plan (APP) for CY {{ $data['year'] }}</h1></header>
    <table>
        <colgroup>@for ($i=0; $i<14; $i++)<col>@endfor</colgroup>
        <thead>
            <tr><th rowspan="2">Code<br>(PAP)</th><th rowspan="2">Procurement Program / Project</th><th rowspan="2">PMO / End-User</th><th rowspan="2">Mode of Procurement</th><th colspan="4">Schedule for Each Procurement Activity</th><th rowspan="2">Source of Funds</th><th colspan="4">Estimated Budget (PhP)</th><th rowspan="2">Remarks<br>(Brief description of Program/Project)</th></tr>
            <tr><th>Ads/Post of IB/REI</th><th>Sub/Open of Bids</th><th>Notice of Award</th><th>Contract Signing</th><th>Total</th><th>Personnel Outlay</th><th>MOOE</th><th>CO</th></tr>
        </thead>
        <tbody>
            @foreach ($data['items'] as $item)<tr>
                @foreach (\App\Services\CompetitiveBiddingAppService::FIELDS as $field)
                    <td @class(['amount' => in_array($field, ['budget','personnel','mooe','co'])])>{{ in_array($field, ['budget','personnel','mooe','co']) && isset($item[$field]) && $item[$field] !== '' ? number_format((float) $item[$field], 2) : ($item[$field] ?? '') }}</td>
                @endforeach
            </tr>@endforeach
        </tbody>
        <tfoot><tr><td colspan="9" class="amount">TOTAL</td><td class="amount">{{ number_format(array_sum(array_map(fn ($item) => (float) ($item['budget'] ?? 0), $data['items'])), 2) }}</td><td colspan="4"></td></tr></tfoot>
    </table>
    <div class="signatures">
        @foreach (\App\Services\CompetitiveBiddingAppService::SLOTS as $slot => $label)
            <section><strong>{{ $label }}:</strong><span class="signature-line" data-app-signature-slot="{{ $slot }}"></span><div class="printed-name">{{ $data[$slot.'_name'] }}</div><div>{{ $data[$slot.'_title'] }}</div></section>
        @endforeach
    </div>
</article>
