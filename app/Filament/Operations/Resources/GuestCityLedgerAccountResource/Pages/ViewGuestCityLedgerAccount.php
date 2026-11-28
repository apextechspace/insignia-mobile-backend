<?php

namespace App\Filament\Operations\Resources\GuestCityLedgerAccountResource\Pages;

use App\Filament\Operations\Resources\GuestCityLedgerAccountResource;
use App\Models\Booking;
use App\Services\GuestCityLedger;
use Filament\Actions\Action;
use Filament\Forms\Components;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ViewGuestCityLedgerAccount extends ViewRecord
{
    protected static string $resource = GuestCityLedgerAccountResource::class;

    public function getTitle(): string { return 'Guest City Ledger · '.$this->record->guest_name; }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Prepaid balance')->description('This is guest money held for future accommodation. A credit is not room revenue; revenue is recorded only when balance is applied to a specific booking.')->schema([
                TextEntry::make('account_reference')->label('Account')->copyable(),
                TextEntry::make('guest_name')->label('Guest / company'),
                TextEntry::make('phone')->placeholder('Not recorded'),
                TextEntry::make('email')->placeholder('Not recorded'),
                TextEntry::make('available_balance')->label('Available balance')->money('NGN')->state(fn () => GuestCityLedger::balance($this->record) / 100),
                TextEntry::make('status')->badge(),
                TextEntry::make('notes')->label('Opening notes')->placeholder('None')->columnSpanFull(),
            ])->columns(3),
            Section::make('Ledger history')->schema([
                RepeatableEntry::make('entries')->state(fn () => $this->record->entries()->with(['booking.room', 'enteredBy'])->latest('id')->get()->map(fn ($entry) => [
                    'posted_at' => $entry->posted_at, 'type' => ucfirst($entry->type), 'reference' => $entry->reference,
                    'amount' => $entry->amount_kobo / 100, 'booking' => $entry->booking ? '#'.$entry->booking->id.' · '.($entry->booking->room?->name ?? 'Room') : 'Prepaid funds received',
                    'method' => $entry->payment_method ? (GuestCityLedger::METHODS[$entry->payment_method] ?? strtoupper($entry->payment_method)) : '—',
                    'by' => $entry->enteredBy?->name ?? 'System', 'notes' => $entry->notes,
                ])->all())->schema([
                    TextEntry::make('posted_at')->label('Posted')->dateTime(), TextEntry::make('type')->badge(), TextEntry::make('reference')->copyable(),
                    TextEntry::make('amount')->money('NGN'), TextEntry::make('booking')->label('Purpose'), TextEntry::make('method')->label('Method'), TextEntry::make('by')->label('Recorded by'),
                    TextEntry::make('notes')->label('Note')->placeholder('—')->columnSpanFull(),
                ])->columns(3),
            ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recordCredit')->label('Record Prepaid Funds')->icon('heroicon-o-plus-circle')->color('success')
                ->modalDescription('Record only money already confirmed in the POS merchant record, bank account or cash count. This does not yet count as accommodation revenue.')
                ->form([
                    Components\TextInput::make('amount')->label('Amount received (₦)')->numeric()->required()->minValue(0.01)->maxValue(99999999.99),
                    Components\Select::make('method')->label('Method')->options(GuestCityLedger::METHODS)->required(),
                    Components\TextInput::make('reference')->label('POS / bank reference')->required()->maxLength(120),
                    Components\FileUpload::make('evidence')->label('Evidence')->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])->maxSize(5120)->storeFiles(false)->required(),
                    Components\Textarea::make('notes')->label('Note')->maxLength(2000),
                    Components\Checkbox::make('confirmed')->label('I matched this payment against the actual POS, bank or cash record')->accepted()->required(),
                ])->action(function (Action $action, array $data) {
                    try {
                        $file = $data['evidence'] ?? null;
                        if (! $file instanceof TemporaryUploadedFile) throw ValidationException::withMessages(['evidence' => 'Upload the payment evidence.']);
                        app(GuestCityLedger::class)->credit($this->record, auth()->user(), (int) round($data['amount'] * 100), $data['method'], $data['reference'], $file->store('city-ledger-evidence', 'local'), $data['notes'] ?? null);
                    } catch (ValidationException $e) { Notification::make()->danger()->title('Prepaid funds were not recorded')->body(collect($e->errors())->flatten()->implode(' '))->persistent()->send(); $action->halt(); }
                    $this->record->refresh(); Notification::make()->success()->title('Prepaid funds recorded')->body('The funds remain a guest balance until applied to a room booking.')->send();
                }),
        ];
    }
}
