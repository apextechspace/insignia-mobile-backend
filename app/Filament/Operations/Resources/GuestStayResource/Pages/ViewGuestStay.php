<?php

namespace App\Filament\Operations\Resources\GuestStayResource\Pages;

use App\Filament\Operations\Resources\GuestStayResource;
use App\Services\AssistedReservationPayments;
use App\Services\GuestCityLedger;
use App\Models\GuestCityLedgerAccount;
use App\Models\Room;
use Filament\Actions\Action;
use Filament\Forms\Components;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ViewGuestStay extends ViewRecord
{
    protected static string $resource = GuestStayResource::class;

    public function getTitle(): string
    {
        return 'Guest details · Booking #' . $this->record->getKey();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reviewExternalArrangement')->label('Confirm Payment Arrangement')
                ->authorize(fn () => (auth()->user()?->isIplAdmin() || auth()->user()?->isDeliveryPartner()) && $this->record->external_source && $this->record->status === 'confirmed' && !$this->record->checked_in_at && !$this->record->paid_at && !\App\Services\ExternalReservations::channelPaymentVerified($this->record))
                ->form([
                    Components\Select::make('mode')->label('Confirmed arrangement')->options(array_intersect_key(\App\Services\ExternalReservations::ARRANGEMENTS, array_flip(['pay_at_property', 'channel_collects'])))->required(),
                    Components\Textarea::make('note')->label('Channel confirmation reference and reason')->required()->maxLength(2000),
                ])->action(function (Action $action, array $data) {
                    try { app(\App\Services\ExternalReservations::class)->setPaymentArrangement($this->record, auth()->user(), $data['mode'], $data['note']); }
                    catch (ValidationException $exception) { Notification::make()->danger()->title($exception->getMessage())->send(); $action->halt(); }
                    $this->record->refresh();
                    Notification::make()->success()->title('Payment arrangement reviewed and recorded')->send();
                }),
            Action::make('cancelExternalReservation')->label('Record Channel Cancellation')->color('danger')
                ->authorize(fn () => (auth()->user()?->isIplAdmin() || auth()->user()?->isDeliveryPartner()) && $this->record->external_source && $this->record->status === 'confirmed' && !$this->record->checked_in_at)
                ->modalDescription('First confirm cancellation in the original channel. This releases the dates locally, preserves all payment evidence and does not issue a refund. Owner finance must handle records with property payment evidence.')
                ->form([
                    Components\Textarea::make('note')->label('Channel cancellation reference and reason')->required()->minLength(10)->maxLength(2000),
                    Components\Checkbox::make('confirmed')->label('I confirmed this cancellation in the original channel')->accepted()->required(),
                ])->action(function (Action $action, array $data) {
                    try { app(\App\Services\ExternalReservations::class)->cancel($this->record, auth()->user(), $data['note']); }
                    catch (ValidationException $exception) { Notification::make()->danger()->title($exception->getMessage())->send(); $action->halt(); }
                    $this->record->refresh();
                    Notification::make()->success()->title('Channel cancellation recorded. No refund was issued.')->send();
                }),
            Action::make('verifyChannelGuestPayment')->label('Verify Guest Channel Payment')->icon('heroicon-o-shield-check')
                ->authorize(fn () => AssistedReservationPayments::canVerify(auth()->user()) && $this->record->external_source
                    && ($this->record->external_details['payment_arrangement'] ?? null) === 'channel_collects'
                    && !\App\Services\ExternalReservations::channelPaymentVerified($this->record) && $this->record->status === 'confirmed')
                ->modalDescription('Verify that the channel has collected the full accommodation payment from the guest. This does not confirm a payout to Insignia or post cash income. Never request a second guest payment just because a channel payout is pending.')
                ->form([
                    Components\FileUpload::make('evidence')->label('Channel payment confirmation')->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])->maxSize(5120)->storeFiles(false)->required(),
                    Components\Textarea::make('note')->label('Verification note / channel payment reference')->required()->maxLength(2000),
                    Components\Checkbox::make('verified')->label('I checked the original channel record and confirmed full guest payment for this room')->accepted()->required(),
                ])->action(function (array $data) {
                    abort_unless(AssistedReservationPayments::canVerify(auth()->user()), 403);
                    $file = $data['evidence'];
                    abort_unless($file instanceof TemporaryUploadedFile, 422);
                    app(\App\Services\ExternalReservations::class)->verifyGuestChannelPayment($this->record, auth()->user(), $file->store('external-channel-evidence', 'local'), $data['note']);
                    $this->record->refresh();
                    Notification::make()->success()->title('Guest channel payment verified. Property payout remains separate.')->send();
                }),
            Action::make('downloadChannelEvidence')->label('Download Channel Evidence')
                ->authorize(fn () => AssistedReservationPayments::canVerify(auth()->user()) && filled($this->record->external_details['guest_payment_evidence'] ?? null))
                ->action(function () {
                    abort_unless(AssistedReservationPayments::canVerify(auth()->user()), 403);
                    $path = $this->record->external_details['guest_payment_evidence'];
                    abort_unless(Storage::disk('local')->exists($path), 404);
                    return Storage::disk('local')->download($path);
                }),
            Action::make('printRegistrationCard')->label('Print Registration Card')->icon('heroicon-o-printer')
                ->authorize(fn () => GuestStayResource::canViewAny())
                ->url(fn () => route('guest-registration-card.print', ['booking' => $this->record->id]))->openUrlInNewTab(),
            Action::make('sendGuestWhatsApp')->label('Send Guest Update via WhatsApp')->icon('heroicon-o-chat-bubble-left-right')
                ->authorize(fn () => GuestStayResource::canViewAny())
                ->visible(fn () => \App\Services\ReservationGuestCommunication::whatsappUrl($this->record) !== null)
                ->tooltip('Opens a prefilled message. Review it and press Send in WhatsApp.')
                ->url(fn () => \App\Services\ReservationGuestCommunication::whatsappUrl($this->record))->openUrlInNewTab(),
            Action::make('approveDepositException')->label('Approve Deposit Exception')
                ->authorize(fn () => auth()->user()?->isIplAdmin()
                    && AssistedReservationPayments::canCollect($this->record)
                    && $this->record->status === 'pending'
                    && AssistedReservationPayments::verified($this->record) > 0)
                ->form([Components\Textarea::make('reason')->required()->maxLength(1000)])
                ->action(function (Action $action, array $data): void {
                    try {
                        app(AssistedReservationPayments::class)->approveDepositException($this->record, auth()->user(), $data['reason']);
                    } catch (ValidationException $exception) {
                        Notification::make()->danger()->title($exception->getMessage())->send();
                        $action->halt();
                    }
                    $this->record->refresh();
                    Notification::make()->success()->title('Deposit exception approved. Dates secured.')->send();
                }),
            Action::make('recordPosPayment')
                ->label(fn (): string => $this->record->external_source ? 'Record Guest Payment' : ($this->record->status === 'confirmed' ? 'Collect Balance' : 'Record Payment'))
                ->authorize(fn () => GuestStayResource::canViewAny() && AssistedReservationPayments::canCollect($this->record))
                ->disabled(fn () => AssistedReservationPayments::pending($this->record)->exists())
                ->tooltip(fn () => AssistedReservationPayments::pending($this->record)->exists() ? 'A proof is already awaiting verification. Verify or reject it before recording another payment.' : null)
                ->modalDescription(fn (): string => $this->record->status === 'confirmed'
                    ? 'Outstanding balance: ₦'.number_format(AssistedReservationPayments::balance($this->record) / 100, 2).'. Upload proof after collecting payment. Check-in opens once the full balance is verified.'
                    : 'The first receipt uploaded during an active hold gives staff two hours to verify the payment.')
                ->fillForm(fn (): array => $this->record->status === 'confirmed'
                    ? ['amount' => number_format(AssistedReservationPayments::balance($this->record) / 100, 2, '.', '')]
                    : [])
                ->form([
                    Components\Select::make('method')->label('Payment method')->options(AssistedReservationPayments::METHODS)
                        ->default('pos')->required(),
                    Components\TextInput::make('amount')->label('Amount received (₦)')
                        ->readOnly(fn () => !$this->record->external_source && $this->record->status === 'confirmed')
                        ->required()->numeric()->rules(['decimal:0,2'])->minValue(0.01)->maxValue(99999999.99),
                    Components\TextInput::make('reference')->label('Bank transaction reference')->required()->maxLength(120),
                    Components\DatePicker::make('transaction_date')->required()
                        ->maxDate(fn () => today(config('property.timezone'))),
                    Components\FileUpload::make('proof')->label('Payment proof')->image()->maxSize(5120)->storeFiles(false)->required(),
                ])
                ->action(function (array $data): void {
                    abort_unless(GuestStayResource::canViewAny(), 403);
                    $file = $data['proof'] ?? null;
                    $reference = strtoupper(trim($data['reference']));
                    if (! $file instanceof TemporaryUploadedFile || $reference === '') {
                        throw ValidationException::withMessages(['data.proof' => 'A receipt and transaction reference are required.']);
                    }
                    $reviewExpiry = DB::transaction(function () use ($data, $file, $reference) {
                        Room::whereKey($this->record->room_id)->lockForUpdate()->firstOrFail();
                        $stay = $this->record->newQuery()->lockForUpdate()->findOrFail($this->record->id);
                        abort_unless(AssistedReservationPayments::canCollect($stay), 409);
                        if (AssistedReservationPayments::pending($stay)->exists()) {
                            throw ValidationException::withMessages(['data.reference' => 'A proof is already awaiting verification. Verify or reject it before recording another payment.']);
                        }
                        $amount = AssistedReservationPayments::kobo($data['amount']);
                        if (!$stay->external_source && $stay->status === 'confirmed' && $amount !== AssistedReservationPayments::balance($stay)) {
                            throw ValidationException::withMessages(['data.amount' => 'Record the full outstanding balance for this confirmed reservation.']);
                        }
                        if ($amount > AssistedReservationPayments::balance($stay)) {
                            throw ValidationException::withMessages(['data.amount' => 'The amount exceeds the outstanding balance.']);
                        }
                        if (AssistedReservationPayments::entries($stay)->where('reference', $reference)->whereNull('rejected_at')->exists()
                            || DB::table('manual_payment_reviews')->whereRaw('UPPER(TRIM(bank_transaction_reference)) = ?', [$reference])->exists()
                            || DB::table('assisted_reservation_payments')->whereRaw('UPPER(TRIM(reference)) = ?', [$reference])->whereNull('rejected_at')->exists()) {
                            throw ValidationException::withMessages(['data.reference' => 'This transaction reference is already recorded. It cannot be submitted again.']);
                        }
                        $reviewExpiry = AssistedReservationPayments::reviewHoldExpiry($stay);
                        $path = $file->store('assisted-pos-receipts', 'local');
                        abort_unless(is_string($path) && $path !== '', 500);
                        DB::table('assisted_reservation_payments')->insert([
                            'booking_id' => $stay->id, 'submitted_by' => auth()->id(), 'amount_kobo' => $amount,
                            'reference' => $reference, 'transaction_date' => $data['transaction_date'], 'proof_path' => $path,
                            'method' => $data['method'],
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                        if ($reviewExpiry) {
                            $stay->update(['hold_expires_at' => $reviewExpiry]);
                        }
                        \App\Services\ReservationGuestCommunication::sendUpdate($stay, 'proof_submitted');

                        return $reviewExpiry;
                    });
                    $this->record->refresh();
                    Notification::make()->success()->title('Payment proof submitted for verification')
                        ->body($reviewExpiry ? 'Room held until '.$reviewExpiry->timezone(config('property.timezone'))->format('g:i A T').'.' : null)
                        ->send();
                }),
            Action::make('applyCityLedger')
                ->label('Use Guest City Ledger')->icon('heroicon-o-wallet')
                ->color('success')
                ->authorize(fn () => GuestCityLedger::canManage(auth()->user()) && AssistedReservationPayments::canCollect($this->record))
                ->modalDescription(fn () => 'This applies the full outstanding room amount (₦'.number_format(AssistedReservationPayments::balance($this->record) / 100, 2).') from verified prepaid guest funds. It cannot be undone here.')
                ->form([
                    Components\Select::make('account_id')->label('Prepaid account')->searchable()->required()
                        ->options(fn () => GuestCityLedgerAccount::where('status', 'active')->orderBy('guest_name')->get()->mapWithKeys(fn ($account) => [$account->id => $account->guest_name.' · '.$account->account_reference.' · Available ₦'.number_format(GuestCityLedger::balance($account) / 100, 2)])->all()),
                    Components\Textarea::make('notes')->label('Booking note')->maxLength(2000),
                    Components\Checkbox::make('confirmed')->label('I confirmed the guest wants this prepaid balance used for this complete room booking')->accepted()->required(),
                ])
                ->action(function (Action $action, array $data): void {
                    try {
                        $account = GuestCityLedgerAccount::findOrFail($data['account_id']);
                        app(GuestCityLedger::class)->applyToBooking($account, $this->record, auth()->user(), $data['notes'] ?? null);
                    } catch (ValidationException $exception) {
                        Notification::make()->danger()->title('City Ledger was not applied')->body(collect($exception->errors())->flatten()->implode(' '))->persistent()->send(); $action->halt();
                    }
                    $this->record->refresh();
                    Notification::make()->success()->title('Guest City Ledger applied')->body('The room is fully paid from prepaid funds and is ready for normal check-in on the arrival date.')->send();
                }),
            Action::make('downloadPosProof')->label('Download Payment Proof')
                ->authorize(fn () => AssistedReservationPayments::canVerify(auth()->user()))
                ->form([Components\Select::make('payment_id')->label('Payment')->required()
                    ->options(fn () => $this->paymentOptions())])
                ->action(function (array $data) {
                    abort_unless(AssistedReservationPayments::canVerify(auth()->user()), 403);
                    $payment = AssistedReservationPayments::entries($this->record)->where('id', $data['payment_id'])->first();
                    abort_unless($payment && Storage::disk('local')->exists($payment->proof_path), 404);

                    return Storage::disk('local')->download($payment->proof_path);
                }),
            Action::make('verifyPosPayment')->label('Verify Payment')
                ->authorize(fn () => AssistedReservationPayments::canVerify(auth()->user()) && AssistedReservationPayments::canCollect($this->record) && AssistedReservationPayments::pending($this->record)->exists())
                ->form([
                    Components\Select::make('payment_id')->label('Payment')->required()
                        ->options(fn () => $this->paymentOptions(pendingOnly: true)),
                    Components\Checkbox::make('verified')->label('I matched the amount and reference against the POS merchant record or actual bank credit')->accepted(),
                ])
                ->action(function (Action $action, array $data): void {
                    try {
                        app(AssistedReservationPayments::class)->verify($this->record, (int) $data['payment_id'], auth()->user());
                    } catch (ValidationException $exception) {
                        Notification::make()->danger()->title($exception->getMessage())->send();
                        $action->halt();
                    }
                    $this->record->refresh();
                    Notification::make()->success()->title('Payment verified')->send();
                }),
            Action::make('rejectPaymentProof')->label('Reject Incorrect Proof')->color('danger')
                ->authorize(fn () => AssistedReservationPayments::canVerify(auth()->user()) && AssistedReservationPayments::pending($this->record)->exists())
                ->modalDescription('This does not refund or reverse money. It rejects unverified evidence only and preserves the audit history.')
                ->form([
                    Components\Select::make('payment_id')->label('Unverified proof')->required()->options(fn () => $this->paymentOptions(pendingOnly: true)),
                    Components\Textarea::make('reason')->label('Reason')->required()->maxLength(1000),
                ])
                ->action(function (Action $action, array $data) {
                    try { app(AssistedReservationPayments::class)->reject($this->record, (int) $data['payment_id'], auth()->user(), $data['reason']); }
                    catch (ValidationException $exception) { Notification::make()->danger()->title($exception->getMessage())->send(); $action->halt(); }
                    $this->record->refresh();
                    Notification::make()->success()->title('Incorrect proof rejected. Verified payments are unchanged.')->send();
                }),
            Action::make('check_in')->label('Check In')->icon('heroicon-o-arrow-right-on-rectangle')
                ->color('success')->modalHeading('Register guest and check in')
                ->modalSubmitActionLabel('Complete check-in')
                ->form(GuestStayResource::registrationForm())
                ->fillForm(fn () => GuestStayResource::registrationDefaults($this->record))
                ->visible(fn () => GuestStayResource::canCheckIn($this->record))
                ->action(function (array $data): void {
                    GuestStayResource::processStayAction($this->record, 'checkIn', $data);
                    $this->record->refresh();
                }),
            Action::make('check_out')->label('Check Out')->icon('heroicon-o-arrow-left-on-rectangle')
                ->color('warning')->requiresConfirmation()
                ->modalDescription('Collect keys and confirm the guest has departed. This completes the stay and creates the housekeeping checklist.')
                ->visible(fn () => GuestStayResource::canCheckOut($this->record))
                ->action(function (): void {
                    GuestStayResource::processStayAction($this->record, 'checkOut');
                    $this->record->refresh();
                }),
        ];
    }

    private function paymentOptions(bool $pendingOnly = false): array
    {
        return AssistedReservationPayments::entries($this->record)
            ->when($pendingOnly, fn ($query) => $query->whereNull('verified_at')->whereNull('rejected_at'))
            ->get()->mapWithKeys(fn ($payment) => [
                $payment->id => AssistedReservationPayments::METHODS[$payment->method].' · '.$payment->reference.' · ₦'.number_format($payment->amount_kobo / 100, 2),
            ])->all();
    }
}
