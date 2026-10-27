<x-filament-panels::page>
    <div class="space-y-5">
        <section class="rounded-2xl bg-gradient-to-br from-[#34091F] to-[#7D2753] p-6 text-white print:border print:border-gray-300 print:bg-white print:text-black">
            <p class="text-xs font-semibold uppercase tracking-widest text-[#EBCB96] print:text-gray-600">IPL · Owner-only finance</p>
            <h1 class="mt-2 text-2xl font-bold">Master Financial Statement</h1>
            <p class="mt-2 max-w-3xl text-sm text-white/80 print:text-gray-600">
                Recorded operating activity by transaction source. This is not a bank statement, cash reconciliation, or confirmation of an actual refund payout.
            </p>
        </section>

        <div class="grid grid-cols-1 gap-3 rounded-xl bg-white p-4 shadow-sm dark:bg-gray-900 sm:grid-cols-4 print:hidden">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                From
                <input type="date" wire:model.live.debounce.500ms="fromDate" class="mt-1 block w-full rounded-lg border-gray-300 dark:bg-gray-800 dark:text-white" />
            </label>
            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                To
                <input type="date" wire:model.live.debounce.500ms="toDate" class="mt-1 block w-full rounded-lg border-gray-300 dark:bg-gray-800 dark:text-white" />
            </label>
            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                Department
                <select wire:model.live="sourceFilter" class="mt-1 block w-full rounded-lg border-gray-300 dark:bg-gray-800 dark:text-white">
                    <option value="all">All sources</option>
                    <option value="accommodation">Accommodation</option>
                    <option value="dining">Dining</option>
                    <option value="boutique">Boutique</option>
                    <option value="concierge">Concierge</option>
                    <option value="expense">Expenses</option>
                </select>
            </label>
            <div class="flex items-end gap-2">
                <button wire:click="exportCsv" type="button" class="rounded-lg bg-[#4A0E2E] px-4 py-2 text-sm font-semibold text-white hover:bg-[#692447]">Export CSV</button>
                <button type="button" onclick="window.print()" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-800 dark:text-gray-100">Print / PDF</button>
            </div>
        </div>
        @php($money = fn (int $cents) => '₦' . number_format($cents / 100, 2))
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs uppercase tracking-wider text-gray-500">Recorded inflows</p>
                <p class="mt-2 text-2xl font-bold text-emerald-700 dark:text-emerald-400">{{ $money($statement['inflow_cents']) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs uppercase tracking-wider text-gray-500">Refunds and approved expenses</p>
                <p class="mt-2 text-2xl font-bold text-rose-700 dark:text-rose-400">{{ $money($statement['outflow_cents']) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs uppercase tracking-wider text-gray-500">Period net activity</p>
                <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ $money($statement['net_cents']) }}</p>
            </div>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400">
            {{ $statement['from'] }} through {{ $statement['to'] }}. Running figures start at zero for the selected period/filter—not the actual opening bank balance.
            Monthly MCL settlements and petty cash transfers are excluded to avoid double-counting internal movements. Approved expenses are operational records and may not have cleared the bank.
            Refund ledger entries must be reconciled against actual provider/bank evidence separately.
        </p>
        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <table class="min-w-full divide-y divide-gray-200 text-left text-sm dark:divide-gray-800">
                <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-600 dark:bg-gray-800 dark:text-gray-200">
                    <tr>
                        <th class="px-3 py-3">Date</th>
                        <th class="px-3 py-3">Source</th>
                        <th class="px-3 py-3">Type</th>
                        <th class="px-3 py-3">Reference</th>
                        <th class="px-3 py-3">Method</th>
                        <th class="px-3 py-3">Description</th>
                        <th class="px-3 py-3 text-right">Amount</th>
                        <th class="px-3 py-3 text-right">Period running</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($statement['rows'] as $row)
                    <tr>
                        <td class="whitespace-nowrap px-3 py-3 text-gray-700 dark:text-gray-200">{{ $row['date'] }}</td>
                        <td class="px-3 py-3 capitalize text-gray-700 dark:text-gray-200">{{ $row['source'] }}</td>
                        <td class="px-3 py-3 capitalize text-gray-700 dark:text-gray-200">{{ $row['type'] }}</td>
                        <td class="px-3 py-3 text-gray-700 dark:text-gray-200">{{ $row['reference'] }}</td>
                        <td class="px-3 py-3 text-gray-700 dark:text-gray-200">{{ $row['method'] }}</td>
                        <td class="px-3 py-3 text-gray-700 dark:text-gray-200">{{ $row['description'] }}</td>
                        <td class="whitespace-nowrap px-3 py-3 text-right font-semibold {{ $row['amount_cents'] < 0 ? 'text-rose-700 dark:text-rose-400' : 'text-emerald-700 dark:text-emerald-400' }}">
                            {{ $money($row['amount_cents']) }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ $money($row['running_cents']) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="px-3 py-10 text-center text-gray-500">No recorded transactions in this date range and source.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
