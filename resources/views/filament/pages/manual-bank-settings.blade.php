<x-filament-panels::page>
    <div class="mx-auto max-w-3xl space-y-5">
        <section class="rounded-2xl bg-gradient-to-br from-[#34091F] to-[#7D2753] p-6 text-white">
            <p class="text-xs font-semibold uppercase tracking-widest text-[#EBCB96]">IPL Finance · Owner settings</p>
            <h1 class="mt-2 text-2xl font-bold">Manual Bank Transfer Account</h1>
            <p class="mt-2 text-sm text-white/80">This account appears to guests across suite, boutique, dining and concierge payments. Verify ownership with your bank before activating. Changing details does not verify historical receipts.</p>
        </section>
        <form wire:submit.prevent="save" class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200">Bank name
                <input wire:model="bankName" type="text" maxlength="120" required autocomplete="off" class="mt-1 w-full rounded-lg border-gray-300 dark:bg-gray-800 dark:text-white" />
                @error('bankName') <span class="text-sm text-rose-600">{{ $message }}</span> @enderror
            </label>
            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200">Account number (10 digits)
                <input wire:model="accountNumber" type="text" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" required autocomplete="off" class="mt-1 w-full rounded-lg border-gray-300 dark:bg-gray-800 dark:text-white" />
                @error('accountNumber') <span class="text-sm text-rose-600">{{ $message }}</span> @enderror
            </label>
            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200">Beneficiary account name
                <input wire:model="accountName" type="text" maxlength="160" required autocomplete="off" class="mt-1 w-full rounded-lg border-gray-300 dark:bg-gray-800 dark:text-white" />
                @error('accountName') <span class="text-sm text-rose-600">{{ $message }}</span> @enderror
            </label>
            <label class="flex items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/20 dark:text-amber-200">
                <input wire:model="active" type="checkbox" class="mt-1 rounded border-amber-400" />
                <span><strong>Activate manual bank transfers</strong><span class="mt-1 block">Only enable after confirming the bank account belongs to the authorized business beneficiary. Leave unchecked to hide account details and block receipt submissions.</span></span>
            </label>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-xs text-gray-500 dark:text-gray-400">Every save records a new account version and the authenticated IPL user. Never enter test or placeholder details.</p>
                <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-[#4A0E2E] px-5 py-3 text-sm font-semibold text-white hover:bg-[#722D52]">Save bank transfer settings</button>
            </div>
        </form>
    </div>
</x-filament-panels::page>