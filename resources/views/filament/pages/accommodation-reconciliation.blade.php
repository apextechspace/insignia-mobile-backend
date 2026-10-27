<x-filament-panels::page>
    <div class="space-y-4">
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Accommodation ledger exception review</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                This report is read-only. Resolve discrepancies only after verifying original payment, refund, and bank evidence.
                Cancelling a reservation is not evidence of a completed refund.
            </p>
            <p class="mt-3 text-sm font-semibold {{ $exceptions->isEmpty() ? 'text-emerald-600' : 'text-amber-600' }}">
                {{ $exceptions->count() }} {{ $exceptions->count() === 1 ? 'exception' : 'exceptions' }} found
            </p>
        </div>
        <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-600 dark:bg-gray-800 dark:text-gray-200">
                    <tr>
                        <th class="px-4 py-3">Booking</th>
                        <th class="px-4 py-3">Payment Reference</th>
                        <th class="px-4 py-3">Issue</th>
                        <th class="px-4 py-3">Required Review</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($exceptions as $entry)
                        <tr>
                            <td class="px-4 py-3 text-gray-900 dark:text-white">#{{ $entry['booking_id'] }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $entry['reference'] }}</td>
                            <td class="px-4 py-3 font-medium text-amber-700 dark:text-amber-300">{{ str_replace('_', ' ', $entry['issue']) }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $entry['description'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-gray-600 dark:text-gray-300">No exceptions detected among the checked records.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
