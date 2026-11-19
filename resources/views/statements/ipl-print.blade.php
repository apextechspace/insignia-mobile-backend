<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Insignia Court - {{ $reportTitle ?? 'Master Statement' }} {{ $statement['from'] }} to {{ $statement['to'] }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f4f1f3; color: #25212a; font: 13px/1.5 Arial, Helvetica, sans-serif; }
        .toolbar { max-width: 1180px; margin: 20px auto; display: flex; gap: 12px; align-items: center; padding: 0 20px; }
        button { background: #4a0e2e; color: white; border: 0; border-radius: 6px; padding: 11px 18px; cursor: pointer; font-weight: 700; }
        .sheet { max-width: 1180px; margin: 20px auto; padding: 32px; background: white; }
        header { display: flex; justify-content: space-between; gap: 24px; border-bottom: 3px solid #4a0e2e; padding-bottom: 20px; }
        header img { width: 160px; height: 76px; object-fit: contain; }
        h1 { font-size: 23px; margin: 0 0 8px; color: #4a0e2e; }
        h2 { font-size: 15px; margin: 24px 0 10px; }
        p { margin: 5px 0; }
        .meta, .note { color: #5c5560; font-size: 11px; }
        .summary { display: flex; gap: 16px; margin: 24px 0; }
        .metric { flex: 1; border: 1px solid #d9cbd3; padding: 14px; border-radius: 6px; }
        .metric strong { display: block; font-size: 23px; margin-top: 6px; color: #4a0e2e; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th { background: #f2ebef; color: #4a0e2e; font-size: 10px; text-align: left; }
        th, td { padding: 9px 6px; border-bottom: 1px solid #ded8dd; vertical-align: top; overflow-wrap: anywhere; }
        td { font-size: 10px; }
        th.money, td.money { text-align: right; white-space: nowrap; overflow-wrap: normal; }
        .negative { color: #a51d38; }
        tfoot { font-weight: 700; }
        .empty { text-align: center; padding: 24px; }
        .monthly-chart { margin: 20px 0; break-inside: avoid; }
        .monthly-row { margin: 8px 0; }
        .monthly-label { display: flex; justify-content: space-between; font-size: 11px; }
        .monthly-track { height: 8px; background: #f2ebef; margin-top: 3px; }
        .monthly-bar { height: 8px; background: #4a0e2e; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        footer { border-top: 1px solid #ded8dd; margin-top: 24px; padding-top: 12px; }
        @page { size: A4 landscape; margin: 12mm; }
        @media print {
            body { background: white; color: #111; }
            .toolbar { display: none !important; }
            .sheet { max-width: none; margin: 0; padding: 0; }
            thead { display: table-header-group; }
            tfoot { display: table-row-group; }
            tr, .metric, header { break-inside: avoid; }
            .summary { break-inside: avoid; }
            th { background: #f2ebef !important; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>
@php($money = fn (int $cents) => 'NGN '.number_format($cents / 100, 2))
<div class="toolbar">
    <button type="button" onclick="window.print()">Print / Save as PDF</button>
    <span class="note">Use landscape orientation. For a clean copy, disable browser headers and footers.</span>
</div>
<main class="sheet">
    <header>
        <div>
            <h1>{{ $reportTitle ?? 'Master Financial Statement' }}</h1>
            <p><strong>Insignia Court</strong> &middot; {{ $reportAudience ?? 'IPL owner statement' }}</p>
            <p>Period: {{ $statement['from'] }} to {{ $statement['to'] }}</p>
            <p>Department: {{ $statement['source'] === 'all' ? 'All sources' : ucfirst($statement['source']) }}</p>
            <p class="meta">Generated: {{ $generatedAt->format('d M Y, g:i A') }} (Nigeria time)</p>
        </div>
        <img src="{{ asset('images/logo-trimmed.png') }}" alt="Insignia Court">
    </header>
    <div class="summary">
        <div class="metric">Recorded inflows<strong>{{ $money($statement['inflow_cents']) }}</strong></div>
        <div class="metric">{{ $outflowLabel ?? 'Refunds and approved expenses' }}<strong>{{ $money($statement['outflow_cents']) }}</strong></div>
        <div class="metric">Period net activity<strong>{{ $money($statement['net_cents']) }}</strong></div>
    </div>
    @if (isset($reportAudience))
        @php($months = $statement['rows']->filter(fn ($row) => $row['amount_cents'] > 0)->groupBy(fn ($row) => substr($row['date'], 0, 7))->map(fn ($rows) => $rows->sum('amount_cents')))
        @if ($months->isNotEmpty())
        <section class="monthly-chart"><h2>Verified receipts by month</h2>
        @foreach ($months as $month => $amount)<div class="monthly-row"><div class="monthly-label"><span>{{ \Carbon\CarbonImmutable::parse($month.'-01')->format('M Y') }}</span><strong>{{ $money($amount) }}</strong></div><div class="monthly-track"><div class="monthly-bar" style="width: {{ max(1, round(100 * $amount / max(1, $months->max()))) }}%"></div></div></div>@endforeach
        </section>
        @endif
    @endif
    <h2>Transaction details ({{ $statement['rows']->count() }})</h2>
    <table>
        <colgroup><col style="width:12%"><col style="width:10%"><col style="width:8%"><col style="width:16%"><col style="width:10%"><col style="width:16%"><col style="width:14%"><col style="width:14%"></colgroup>
        <thead><tr><th>Date</th><th>Source</th><th>Type</th><th>Reference</th><th>Method</th><th>Description</th><th class="money">Amount (NGN)</th><th class="money">Period running (NGN)</th></tr></thead>
        <tbody>
        @forelse ($statement['rows'] as $row)
            <tr>
                <td>{{ $row['date'] }}</td><td>{{ ucfirst($row['source']) }}</td><td>{{ ucfirst($row['type']) }}</td>
                <td>{{ $row['reference'] }}</td><td>{{ ucwords(str_replace('_', ' ', $row['method'])) }}</td><td>{{ $row['description'] }}</td>
                <td class="money {{ $row['amount_cents'] < 0 ? 'negative' : '' }}">{{ number_format($row['amount_cents'] / 100, 2) }}</td>
                <td class="money">{{ number_format($row['running_cents'] / 100, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty">No recorded transactions for the selected period and department.</td></tr>
        @endforelse
        </tbody>
        <tfoot><tr><td colspan="6">Period net activity</td><td class="money">{{ number_format($statement['net_cents'] / 100, 2) }}</td><td class="money">{{ number_format($statement['net_cents'] / 100, 2) }}</td></tr></tfoot>
    </table>
    <footer class="note">
        <p>Recorded operating activity, not a bank statement or confirmation of refund payout. Running figures start at zero for this period and department; they are not an opening or closing bank balance.</p>
        <p>Monthly VRL settlements and operating float transfers are excluded to avoid counting internal movements twice. Approved expenses may not have cleared the bank. Reconcile recorded refunds against provider or bank evidence.</p>
        <p>Verified assisted deposits and balances appear on their verification dates; the final booking total is not counted again. Transaction times use Nigeria time.</p>
    </footer>
</main>
</body>
</html>
