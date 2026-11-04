<style>
    .aip-snapshot { width:400mm; max-width:none; min-height:270mm; padding:10mm 8mm 12mm; margin:0 auto; background:#fff; color:#111; box-sizing:border-box; font:7.2pt/1.2 Arial,Helvetica,sans-serif; }
    .aip-snapshot * { box-sizing:border-box; }
    .aip-snapshot header { text-align:center; margin-bottom:12px; }
    .aip-snapshot h1 { margin:0 0 3px; color:#111; font:700 12pt/1.15 Arial,sans-serif; }
    .aip-snapshot header p { margin:2px 0; }
    .aip-snapshot-meta { display:flex; justify-content:space-between; gap:16px; margin:0 0 8px; font-size:9pt; }
    .aip-snapshot table { width:100%; border-collapse:collapse; table-layout:fixed; color:#111; }
    .aip-snapshot col:nth-child(1) { width:8%; }
    .aip-snapshot col:nth-child(2) { width:15%; }
    .aip-snapshot col:nth-child(3) { width:8%; }
    .aip-snapshot col:nth-child(4), .aip-snapshot col:nth-child(5) { width:6.5%; }
    .aip-snapshot col:nth-child(6) { width:13%; }
    .aip-snapshot col:nth-child(7) { width:6%; }
    .aip-snapshot col:nth-child(8), .aip-snapshot col:nth-child(9), .aip-snapshot col:nth-child(10), .aip-snapshot col:nth-child(11) { width:5.5%; }
    .aip-snapshot col:nth-child(12), .aip-snapshot col:nth-child(13) { width:5%; }
    .aip-snapshot col:nth-child(14) { width:5%; }
    .aip-snapshot th, .aip-snapshot td { border:1px solid #333; padding:4px; vertical-align:middle; overflow-wrap:anywhere; }
    .aip-snapshot thead th { text-align:center; font-weight:700; }
    .aip-snapshot thead tr:first-child th { height:30px; }
    .aip-snapshot thead tr:nth-child(2) th { height:38px; }
    .aip-snapshot .aip-snapshot-number th { height:20px; font-weight:400; }
    .aip-snapshot .aip-snapshot-section th { text-align:left; font-size:8pt; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .aip-snapshot .aip-snapshot-subheading th { padding-left:12px; font-size:7.5pt; }
    .aip-snapshot .aip-snapshot-amount { text-align:right; }
    .aip-snapshot .aip-snapshot-total { font-weight:700; }
    .aip-snapshot-signatures { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:25mm; margin:26mm 5mm 0; break-inside:avoid; }
    .aip-snapshot-signatures section { min-width:0; text-align:center; }
    .aip-snapshot-signatures .aip-snapshot-label { display:block; margin-bottom:6mm; text-align:left; font-size:9pt; font-weight:700; }
    .aip-snapshot-signature-visual { height:10mm; display:flex; align-items:end; justify-content:center; }
    .aip-snapshot-signature-visual img { max-height:10mm; max-width:70mm; object-fit:contain; }
    .aip-snapshot-name { border-bottom:1px solid #111; padding:2px; font-weight:700; text-transform:uppercase; }
    .aip-snapshot-date { margin-top:8px; text-align:left; }
    @media print {
        @page { size:A4 landscape; margin:6mm; }
        .aip-snapshot { width:285mm !important; min-height:0 !important; padding:0 !important; font-size:5.5pt !important; }
        .aip-snapshot h1 { font-size:10pt !important; }
        .aip-snapshot-meta { font-size:7pt !important; }
        .aip-snapshot th, .aip-snapshot td { padding:2px !important; }
        .aip-snapshot-signatures { margin-top:15mm !important; gap:12mm !important; }
        .aip-snapshot thead { display:table-header-group; }
        .aip-snapshot tr { break-inside:avoid; }
    }
</style>

@php
    $totals = ['ps' => 0, 'mooe' => 0, 'co' => 0, 'all' => 0];
    foreach ($data['items'] as $item) {
        foreach (['ps', 'mooe', 'co'] as $key) {
            $totals[$key] += (float) ($item[$key] ?? 0);
        }
    }
    $totals['all'] = $totals['ps'] + $totals['mooe'] + $totals['co'];
    $amount = fn ($value) => $value !== null && $value !== '' ? number_format((float) $value, 2) : '';
    $visibleSections = app(\App\Services\AnnualInvestmentProgramService::class)->visibleSections($data);
@endphp

<article class="aip-snapshot">
    <header>
        <h1>CY {{ $data['year'] }} Annual Investment Program (AIP)</h1>
        <p>By Program / Project / Activity by Sector</p>
        <p>As of January 1 to December 31, {{ $data['year'] }}</p>
    </header>
    <div class="aip-snapshot-meta">
        <span>Municipality: {{ $data['municipality'] }}</span>
        <span>{{ ! empty($data['climate_change']) ? '[X]' : '[ ]' }} Climate Change Expenditure (Please check box if your LGU does not have any climate change expenditure)</span>
    </div>
    <table>
        <colgroup>@for ($column = 1; $column <= 14; $column++)<col>@endfor</colgroup>
        <thead>
            <tr>
                <th rowspan="2">AIP<br>REFERENCE<br>CODE</th><th rowspan="2">PROGRAM / PROJECT / ACTIVITY<br>DESCRIPTION</th>
                <th rowspan="2">Implementing<br>Office / Department</th><th colspan="2">Schedule of Implementation</th>
                <th rowspan="2">Expected Output</th><th rowspan="2">FUNDING<br>SOURCE</th>
                <th colspan="4">AMOUNT (in thousand pesos)</th><th colspan="2">AMOUNT OF Climate Change Expenditures (in thousand pesos)</th>
                <th rowspan="2">CC Typology<br>Code</th>
            </tr>
            <tr>
                <th>Starting<br>Date</th><th>Completion<br>Date</th><th>Personal Services<br>(PS)</th>
                <th>Maintenance and Other Operating Expenses<br>(MOOE)</th><th>Capital Outlay<br>(CO)</th><th>TOTAL</th>
                <th>Climate Change<br>Adaptation</th><th>Climate Change<br>Mitigation</th>
            </tr>
            <tr class="aip-snapshot-number">@for ($column = 1; $column <= 14; $column++)<th>({{ $column }})</th>@endfor</tr>
        </thead>
        <tbody>
            @foreach ($visibleSections as $section)
                    <tr class="aip-snapshot-section @if($section['type'] === 'subheading') aip-snapshot-subheading @endif"><th colspan="14" style="background-color:{{ $section['color'] }}">{{ $section['label'] }}</th></tr>
                    @foreach ($section['items'] as $item)
                        @php($rowTotal = (float) ($item['ps'] ?? 0) + (float) ($item['mooe'] ?? 0) + (float) ($item['co'] ?? 0))
                        <tr>
                            <td>{{ $item['reference'] ?? '' }}</td><td>{{ $item['description'] ?? '' }}</td><td>{{ $item['office'] ?? '' }}</td>
                            <td>{{ $item['start'] ?? '' }}</td><td>{{ $item['completion'] ?? '' }}</td><td>{{ $item['output'] ?? '' }}</td>
                            <td>{{ $item['funding'] ?? '' }}</td>
                            <td class="aip-snapshot-amount">{{ $amount($item['ps'] ?? null) }}</td>
                            <td class="aip-snapshot-amount">{{ $amount($item['mooe'] ?? null) }}</td>
                            <td class="aip-snapshot-amount">{{ $amount($item['co'] ?? null) }}</td>
                            <td class="aip-snapshot-amount">{{ $amount($rowTotal) }}</td>
                            <td class="aip-snapshot-amount">{{ $amount($item['adaptation'] ?? null) }}</td>
                            <td class="aip-snapshot-amount">{{ $amount($item['mitigation'] ?? null) }}</td>
                            <td>{{ $item['typology'] ?? '' }}</td>
                        </tr>
                    @endforeach
            @endforeach
            <tr class="aip-snapshot-total">
                <td></td><td>TOTAL</td><td colspan="5"></td>
                <td class="aip-snapshot-amount">{{ $amount($totals['ps']) }}</td>
                <td class="aip-snapshot-amount">{{ $amount($totals['mooe']) }}</td>
                <td class="aip-snapshot-amount">{{ $amount($totals['co']) }}</td>
                <td class="aip-snapshot-amount">{{ $amount($totals['all']) }}</td><td colspan="3"></td>
            </tr>
        </tbody>
    </table>

    <div class="aip-snapshot-signatures">
        @foreach (\App\Services\AnnualInvestmentProgramService::SLOTS as $slot => $label)
            <section>
                <strong class="aip-snapshot-label">{{ $label }}:</strong>
                <div class="aip-snapshot-signature-visual" data-aip-signature-slot="{{ $slot }}"></div>
                <div class="aip-snapshot-name">{{ $data[$slot.'_name'] ?? '' }}</div>
                <div>{{ $data[$slot.'_title'] ?? '' }}</div>
                <div class="aip-snapshot-date">Date: {{ $data[$slot.'_date'] ?? '' }}</div>
            </section>
        @endforeach
    </div>
</article>
