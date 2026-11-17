<?php

namespace App\Filament\Operations\Resources\GuestStayResource\Pages;

use App\Filament\Operations\Resources\GuestStayResource;
use App\Services\AssistedReservationPayments;
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
                ->label(fn (): string => $this->record->status === 'confirmed' ? 'Collect Balance' : 'Record POS Payment')
                ->authorize(fn () => GuestStayResource::canViewAny() && AssistedReservationPayments::canCollect($this->record))
                ->modalDescription(fn (): string => $this->record->status === 'confirmed'
                    ? 'Outstanding balance: ₦'.number_format(AssistedReservationPayments::balance($this->record) / 100, 2).'. Upload the POS receipt after collecting payment. Check-in opens once the full balance is verified.'
                    : 'The first receipt uploaded during an active hold gives staff two hours to verify the payment.')
                ->fillForm(fn (): array => $this->record->status === 'confirmed'
                    ? ['amount' => number_format(AssistedReservationPayments::balance($this->record) / 100, 2, '.', '')]
                    : [])
                ->form([
                    Components\TextInput::make('amount')->label('Amount on POS receipt (₦)')
                        ->required()->numeric()->rules(['decimal:0,2'])->minValue(0.01)->maxValue(99999999.99),
                    Components\TextInput::make('reference')->label('Bank transaction reference')->required()->maxLength(120),
                    Components\DatePicker::make('transaction_date')->required()
                        ->maxDate(fn () => today(config('property.timezone'))),
                    Components\FileUpload::make('proof')->label('POS receipt')->image()->maxSize(5120)->storeFiles(false)->required(),
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
                        $amount = AssistedReservationPayments::kobo($data['amount']);
                        if ($amount > AssistedReservationPayments::balance($stay)) {
                            throw ValidationException::withMessages(['data.amount' => 'The amount exceeds the outstanding balance.']);
                        }
                        if (AssistedReservationPayments::entries($stay)->where('reference', $reference)->exists()) {
                            throw ValidationException::withMessages(['data.reference' => 'This reference is already recorded on the reservation.']);
                        }
                        $reviewExpiry = AssistedReservationPayments::reviewHoldExpiry($stay);
                        $path = $file->store('assisted-pos-receipts', 'local');
                        abort_unless(is_string($path) && $path !== '', 500);
                        DB::table('assisted_reservation_payments')->insert([
                            'booking_id' => $stay->id, 'submitted_by' => auth()->id(), 'amount_kobo' => $amount,
                            'reference' => $reference, 'transaction_date' => $data['transaction_date'], 'proof_path' => $path,
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                        if ($reviewExpiry) {
                            $stay->update(['hold_expires_at' => $reviewExpiry]);
                        }

                        return $reviewExpiry;
                    });
                    $this->record->refresh();
                    Notification::make()->success()->title('POS receipt submitted for verification')
                        ->body($reviewExpiry ? 'Room held until '.$reviewExpiry->timezone(config('property.timezone'))->format('g:i A T').'.' : null)
                        ->send();
                }),
            Action::make('downloadPosProof')->label('Download POS Proof')
                ->authorize(fn () => AssistedReservationPayments::canVerify(auth()->user()))
                ->form([Components\Select::make('payment_id')->label('Payment')->required()
                    ->options(fn () => $this->paymentOptions())])
                ->action(function (array $data) {
                    abort_unless(AssistedReservationPayments::canVerify(auth()->user()), 403);
                    $payment = AssistedReservationPayments::entries($this->record)->where('id', $data['payment_id'])->first();
                    abort_unless($payment && Storage::disk('local')->exists($payment->proof_path), 404);

                    return Storage::disk('local')->download($payment->proof_path);
                }),
            Action::make('verifyPosPayment')->label('Verify POS Payment')
                ->authorize(fn () => AssistedReservationPayments::canVerify(auth()->user()) && AssistedReservationPayments::canCollect($this->record))
                ->form([
                    Components\Select::make('payment_id')->label('Payment')->required()
                        ->options(fn () => $this->paymentOptions(pendingOnly: true)),
                    Components\Checkbox::make('verified')->label('I matched the amount and reference against the merchant transaction record')->accepted(),
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
            ->when($pendingOnly, fn ($query) => $query->whereNull('verified_at'))
            ->get()->mapWithKeys(fn ($payment) => [
                $payment->id => $payment->reference.' · ₦'.number_format($payment->amount_kobo / 100, 2),
            ])->all();
    }
}
