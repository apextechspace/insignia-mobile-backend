<x-filament-panels::page>
    @php($money = fn ($cents) => '₦'.number_format($cents / 100, 2))
    <div class="min-w-0 space-y-5">
        <x-filament::section>
            <x-slot name="heading">Verified Accommodation Receipts</x-slot>
            <x-slot name="description">Room payments only, including verified deposits and balances. This is recorded cash activity before operating costs, not profit, bank reconciliation or future booking value.</x-slot>
            <div class="flex flex-wrap gap-2">
                @foreach (['this_month' => 'This Month', 'last_month' => 'Last Month', 'this_year' => 'This Year', 'last_year' => 'Last Year'] as $period => $label)
                    <x-filament::button color="gray" size="sm" wire:click="setPeriod('{{ $period }}')">{{ $label }}</x-filament::button>
                @endforeach
            </div>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-200">From<input type="date" wire:model.live.debounce.500ms="fromDate" class="mt-1 block w-full rounded-lg border-gray-300 dark:bg-gray-800 dark:text-white" /></label>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-200">To<input type="date" wire:model.live.debounce.500ms="toDate" class="mt-1 block w-full rounded-lg border-gray-300 dark:bg-gray-800 dark:text-white" /></label>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <x-filament::button wire:click="exportCsv">Export CSV</x-filament::button>
                <x-filament::button tag="a" color="gray" target="_blank" rel="noopener" :href="route('accommodation-statement.print', ['from' => $statement['from'], 'to' => $statement['to']])">Print / PDF</x-filament::button>
            </div>
        </x-filament::section>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            @foreach (['Verified room receipts' => $statement['inflow_cents'], 'Recorded room refunds' => $statement['outflow_cents'], 'Receipts less refunds' => $statement['net_cents']] as $label => $value)
                <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900"><p class="text-sm text-gray-600 dark:text-gray-300">{{ $label }}</p><p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ $money($value) }}</p></div>
            @endforeach
        </div>
        @php($months = $statement['rows']->filter(fn ($row) => $row['amount_cents'] > 0)->groupBy(fn ($row) => substr($row['date'], 0, 7))->map(fn ($rows) => $rows->sum('amount_cents')))
        <x-filament::section>
            <x-slot name="heading">Verified Receipts by Month</x-slot>
            <div class="space-y-3">
                @forelse ($months as $month => $amount)
                    <div><div class="mb-1 flex justify-between gap-3 text-sm"><span>{{ \Carbon\CarbonImmutable::parse($month.'-01')->format('M Y') }}</span><strong>{{ $money($amount) }}</strong></div><div class="h-3 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800"><div class="h-full rounded-full bg-[#4A0E2E] dark:bg-pink-400" style="width: {{ max(1, round(100 * $amount / max(1, $months->max()))) }}%"></div></div></div>
                @empty<p class="text-sm text-gray-500 dark:text-gray-400">No verified receipts in this period.</p>@endforelse
            </div>
        </x-filament::section>
        <p class="text-sm text-gray-600 dark:text-gray-300">{{ $statement['from'] }} to {{ $statement['to'] }} · Nigeria time. Only verified receipts count. The final booking total is not counted again after deposits. Pending proofs, operating float, kitchen, shop and concierge transactions are excluded. Monthly management statements and expenses remain separate.</p>
        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <table class="min-w-full text-left text-sm"><thead class="bg-gray-50 dark:bg-gray-800"><tr>@foreach (['Verified date', 'Reference', 'Method', 'Details', 'Amount', 'Period running'] as $heading)<th class="px-4 py-3">{{ $heading }}</th>@endforeach</tr></thead><tbody>
            @forelse ($statement['rows'] as $row)<tr class="border-t border-gray-100 dark:border-gray-800"><td class="whitespace-nowrap px-4 py-3">{{ $row['date'] }}</td><td class="px-4 py-3">{{ $row['reference'] }}</td><td class="px-4 py-3">{{ ucwords(str_replace('_', ' ', $row['method'])) }}</td><td class="px-4 py-3">{{ $row['description'] }}</td><td class="whitespace-nowrap px-4 py-3">{{ $money($row['amount_cents']) }}</td><td class="whitespace-nowrap px-4 py-3">{{ $money($row['running_cents']) }}</td></tr>@empty<tr><td colspan="6" class="p-5 text-gray-500">No recorded accommodation transactions in this period.</td></tr>@endforelse
            </tbody></table>
        </div>
    </div>
</x-filament-panels::page>
