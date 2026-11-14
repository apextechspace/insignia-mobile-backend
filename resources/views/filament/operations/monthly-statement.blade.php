<x-filament-panels::page>
    @php
        $money = fn ($cents) => '₦'.number_format($cents / 100, 2);
    @endphp
    <x-filament::section>
        <x-slot name="heading">{{ $statement->statement_code }}</x-slot>
        <p>{{ str($statement->status)->headline() }} · {{ $draft ? 'Live preview' : 'Submitted statement' }}</p>
        @if ($statement->ipl_query_notes)
            <p class="mt-2">IPL review note: {{ $statement->ipl_query_notes }}</p>
        @endif
        <p>Management fee rate: {{ $statement->management_fee_percentage }}%</p>
        @if ($statement->submitted_at)
            <p>Submitted {{ $statement->submitted_at->timezone(config('property.timezone'))->format('j M Y, g:i A') }}</p>
        @endif
    </x-filament::section>

    @if ($report === null)
        <x-filament::section>
            <x-slot name="heading">Historical statement</x-slot>
            <p>This statement predates transaction snapshots. The original recorded totals are shown below.</p>
            <p>Accommodation: ₦{{ number_format($statement->total_accommodation_revenue, 2) }}</p>
            <p>Expenses: ₦{{ number_format($statement->total_permitted_expenses, 2) }}</p>
            <p>Management fee: ₦{{ number_format($statement->management_fee_amount, 2) }}</p>
            <p>Net payable to IPL: ₦{{ number_format($statement->net_payable_to_ipl, 2) }}</p>
        </x-filament::section>
    @else
        <x-filament::section>
            <x-slot name="heading">Accommodation statement</x-slot>
            <dl class="grid gap-4 sm:grid-cols-3">
                @foreach (['receipts' => 'Accommodation receipts', 'refunds' => 'Refunds', 'adjustments' => 'Adjustments', 'revenue' => 'Net accommodation revenue', 'expenseTotal' => 'Approved expenses', 'fee' => 'Management fee', 'net' => 'Net payable to IPL'] as $key => $label)
                    <div><dt>{{ $label }}</dt><dd class="text-lg font-semibold">{{ $money($report[$key]) }}</dd></div>
                @endforeach
            </dl>
        </x-filament::section>
        <x-filament::section>
            <x-slot name="heading">Imprest reconciliation</x-slot>
            <dl class="grid gap-4 sm:grid-cols-3">
                @foreach (['opening' => 'Opening balance', 'topups' => 'Allocations and top-ups', 'spending' => 'Disbursements', 'returned' => 'Funds returned', 'balance' => 'Expected closing balance', 'actual' => 'Actual closing balance', 'difference' => 'Difference'] as $key => $label)
                    <div><dt>{{ $label }}</dt><dd class="font-semibold">{{ $report[$key] === null ? 'Not recorded' : $money($report[$key]) }}</dd></div>
                @endforeach
            </dl>
            @if ($statement->float_reconciliation_notes)
                <p class="mt-4">{{ $statement->float_reconciliation_notes }}</p>
            @endif
        </x-filament::section>
        @php
            $daily = [];
            foreach ($report['days'] as $date => $values) {
                $daily[$date.' · Revenue'] = $values['revenue'] ?? 0;
                $daily[$date.' · Expenses'] = $values['expenses'] ?? 0;
            }
        @endphp
        @foreach (['Daily revenue and expenses' => $daily, 'Expenses by category' => $report['categories'], 'Imprest balance trend' => $report['floatTrend']] as $heading => $series)
            <x-filament::section>
                <x-slot name="heading">{{ $heading }}</x-slot>
                @php
                    $maximum = max([1, ...array_map('abs', array_values($series))]);
                @endphp
                <div class="space-y-3">
                    @forelse ($series as $label => $value)
                        <div>
                            <div class="flex justify-between gap-4"><span>{{ str_replace('_', ' ', $label) }}</span><span>{{ $money($value) }}</span></div>
                            <div class="h-2 rounded" style="width:{{ abs($value) / $maximum * 100 }}%; background:{{ $value < 0 ? '#b45309' : '#9e3560' }}"></div>
                        </div>
                    @empty
                        <p>No transactions for this period.</p>
                    @endforelse
                </div>
            </x-filament::section>
        @endforeach
        <x-filament::section>
            <x-slot name="heading">Transaction details</x-slot>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead><tr><th>Date</th><th>Type</th><th>Reference</th><th>Amount</th></tr></thead>
                    <tbody>
                        @forelse ($report['rows'] as [$date, $type, $reference, $amount])
                            <tr class="border-t"><td class="py-2">{{ $date }}</td><td>{{ $type }}</td><td>{{ $reference }}</td><td>{{ $money($amount) }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="py-2">No transactions in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
