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
        <form wire:submit="saveDepositPolicy" class="space-y-4 rounded-xl border p-5">
            <h2 class="text-lg font-semibold">Front Desk and VRL deposit policy</h2>
            <p>Applies to new staff reservations. Full verified payment is due before check-in.</p>
            <label class="block"><input type="checkbox" wire:model="depositsEnabled"> Allow deposits to secure room dates</label>
            <div class="space-y-2">
                <label for="deposit-mode">Staff reservation deposit rule</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select id="deposit-mode" wire:model.live="depositMode">
                        <option value="percentage">Percentage of the total stay</option>
                        <option value="night_based">First night (1–6 nights); first three nights (7+ nights)</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>
                <p>Applies to new staff reservations only. Online Flutterwave bookings still require full payment. Existing reservations retain their agreed minimum.</p>
            </div>
            @if ($depositMode === 'percentage')
            <div class="space-y-2" style="max-width: 20rem">
                <label for="minimum-deposit" class="block text-sm font-semibold text-gray-700 dark:text-gray-200">Minimum deposit (%)</label>
                <x-filament::input.wrapper suffix="%" :valid="! $errors->has('minimumDeposit')">
                    <x-filament::input id="minimum-deposit" type="number" step="0.01" min="0.01" max="100" wire:model="minimumDeposit" required />
                </x-filament::input.wrapper>
            </div>
            @else
                <p>A one-night stay requires full payment. Stays of two to six nights require the first night; stays of seven nights or more require the first three nights. Final room prices are used without adding VAT again.</p>
            @endif
            <h3 class="font-semibold">Cancellation Terms — All New Room Reservations</h3>
            <p>These terms apply to new online and staff-assisted bookings when enabled. They do not enable online instalments or change existing reservations.</p>
            <label class="block"><input type="checkbox" wire:model="cancellationTermsEnabled"> Apply the cancellation terms below to new reservations and confirmation emails</label>
            <p>New reservations: for 1–6 nights, the cancellation notice deadline is 48 hours before check-in; later cancellation retains the first night. For 7+ nights, the deadline is seven days before check-in; later cancellation retains the first three nights, limited to money received. A 10% administration fee applies to the eligible refund after used nights and approved charges, with no additional VAT deduction. Eligible post-checkout refunds are processed within 24 hours after checkout. Refunds and room release require management handling; existing reservations retain their saved terms.</p>
            <label class="block"><input type="checkbox" wire:model="lateBookingGraceEnabled"> Extended stays booked within seven days of arrival: allow cancellation until the earlier of 24 hours after booking or scheduled check-in</label>
            <p>Check-in: {{ \Carbon\CarbonImmutable::createFromFormat('H:i', config('property.check_in_time'))->format('g:i A') }}; check-out: noon, Nigeria time. Extended-stay no-show room release is a management review from noon the following day, never an automatic action. Confirm these business terms before enabling them.</p>
            <label class="block"><input type="checkbox" wire:model="vrlCanVerifyPos"> Allow VRL managers to verify assisted POS and bank transfer payments</label>
            <p>Managers must match the amount and reference against the POS merchant record or actual bank credit. IPL still handles deposit exceptions.</p>
            @error('minimumDeposit') <p>{{ $message }}</p> @enderror
            <x-filament::button type="submit">Save deposit policy</x-filament::button>
        </form>
    </div>
</x-filament-panels::page>
