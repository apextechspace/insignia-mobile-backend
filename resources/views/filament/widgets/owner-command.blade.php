<x-filament-widgets::widget>
    <div class="space-y-4">
        <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#34091F] via-[#591539] to-[#7D2753] p-6 text-white shadow-lg md:p-8">
            <div class="pointer-events-none absolute -right-12 -top-20 h-64 w-64 rounded-full border border-white/10"></div>
            <div class="pointer-events-none absolute -bottom-24 right-24 h-56 w-56 rounded-full bg-white/5 blur-2xl"></div>
            <div class="relative flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#EBCB96]">IPL · Ownership oversight</p>
                    <h1 class="mt-2 font-serif text-2xl font-bold tracking-tight md:text-3xl">Executive Command Centre</h1>
                    <p class="mt-2 max-w-xl text-sm text-white/80">Accommodation, ancillary income and exceptions in one owner-only workspace. Figures below reflect recorded transactions, not estimated future earnings.</p>
                </div>
                <div class="rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-sm">
                    <p class="text-xs uppercase tracking-wide text-white/70">Snapshot date</p>
                    <p class="mt-1 font-semibold">{{ now()->format('D, d M Y') }}</p>
                    <p class="text-xs text-white/70">Refresh page for latest figures</p>
                </div>
            </div>
        </section>

        <div class="flex flex-wrap gap-2">
            <a href="{{ \App\Filament\Pages\IplMasterStatement::getUrl(panel: 'admin') }}"
               class="inline-flex items-center rounded-lg bg-[#4A0E2E] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#722D52]">
                Open master financial statement →
            </a>
            <a href="{{ \App\Filament\Pages\ManualBankSettings::getUrl(panel: 'admin') }}"
               class="inline-flex items-center rounded-lg border border-amber-300 bg-amber-50 px-4 py-2.5 text-sm font-semibold text-amber-950 hover:bg-amber-100 dark:border-amber-900 dark:bg-amber-950/20 dark:text-amber-200">
                Manage bank transfer account
            </a>
            <a href="{{ \App\Filament\Pages\AccommodationReconciliationReport::getUrl(panel: 'admin') }}"
               class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-800 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                Review accommodation discrepancies
            </a>
        </div>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <a href="{{ \App\Filament\Resources\ManualTransferReviewResource::getUrl('index') }}" class="group rounded-xl border border-amber-200 bg-amber-50 p-4 transition hover:border-amber-400 hover:shadow-sm dark:border-amber-900 dark:bg-amber-950/30">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">Bank receipts</p>
                <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ $pendingReceipts }}</p>
                <p class="mt-2 text-sm text-amber-800 dark:text-amber-200">Verify credits before approval <span aria-hidden="true">→</span></p>
            </a>
            <a href="{{ \App\Filament\Resources\PaymentReconciliationCaseResource::getUrl('index') }}" class="group rounded-xl border border-rose-200 bg-rose-50 p-4 transition hover:border-rose-400 hover:shadow-sm dark:border-rose-900 dark:bg-rose-950/30">
                <p class="text-xs font-semibold uppercase tracking-wide text-rose-700 dark:text-rose-300">Payment exceptions</p>
                <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ $paymentExceptions }}</p>
                <p class="mt-2 text-sm text-rose-800 dark:text-rose-200">Review disputed or conflicting funds <span aria-hidden="true">→</span></p>
            </a>
            <a href="{{ \App\Filament\Operations\Resources\MonthlyStatementResource::getUrl('index', panel: 'operations') }}" class="group rounded-xl border border-violet-200 bg-violet-50 p-4 transition hover:border-violet-400 hover:shadow-sm dark:border-violet-900 dark:bg-violet-950/30">
                <p class="text-xs font-semibold uppercase tracking-wide text-violet-700 dark:text-violet-300">MCL statements</p>
                <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ $statementsAwaitingReview }}</p>
                <p class="mt-2 text-sm text-violet-800 dark:text-violet-200">Statements awaiting owner review <span aria-hidden="true">→</span></p>
            </a>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400">Manual receipts and provider exceptions are not recognized as paid until independently verified.</p>
    </div>
</x-filament-widgets::widget>
