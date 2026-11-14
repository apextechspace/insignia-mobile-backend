<?php

namespace App\Filament\Operations\Resources\AssetResource\Pages;

use App\Filament\Operations\Resources\AssetResource;
use App\Services\AssetCsvImport;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ListAssets extends ListRecords
{
    protected static string $resource = AssetResource::class;

    private function canImportAssets(): bool
    {
        $user = auth()->user();

        return $user && ($user->isIplAdmin() || $user->isDeliveryPartner());
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Register New Asset'),
            Actions\Action::make('downloadAssetTemplate')
                ->label('Download CSV Template')
                ->authorize(fn (): bool => $this->canImportAssets())
                ->action(function () {
                    abort_unless($this->canImportAssets(), 403);

                    return response()->streamDownload(function (): void {
                        $stream = fopen('php://output', 'w');
                        fputcsv($stream, AssetCsvImport::HEADERS, ',', '"', '');
                        fclose($stream);
                    }, 'asset-register-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
                }),
            Actions\Action::make('importAssets')
                ->label('Import CSV')
                ->authorize(fn (): bool => $this->canImportAssets())
                ->modalSubmitActionLabel('Import Assets')
                ->steps([
                    Forms\Components\Wizard\Step::make('Upload')
                        ->schema([
                            Forms\Components\FileUpload::make('csv')
                                ->label('Asset CSV')
                                ->helperText('Use the CSV template. Enter an existing suite name or leave room blank for storage. Use YYYY-MM-DD for dates. Limit: 500 rows.')
                                ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                                ->maxSize(512)
                                ->storeFiles(false)
                                ->required(),
                            Forms\Components\Placeholder::make('allowed_values')
                                ->label('Accepted values')
                                ->content('Category: electronics, furniture, kitchen_appliance, plumbing, lighting, hvac, other. Condition: brand_new, good, fair, needs_repair, condemned. A blank condition defaults to good.'),
                        ]),
                    Forms\Components\Wizard\Step::make('Preview')
                        ->schema([
                            Forms\Components\Placeholder::make('preview')
                                ->hiddenLabel()
                                ->content(function (Forms\Get $get) {
                                    abort_unless($this->canImportAssets(), 403);
                                    $file = collect($get('csv'))->first();

                                    return $file instanceof TemporaryUploadedFile
                                        ? view('filament.operations.asset-import-preview', ['preview' => app(AssetCsvImport::class)->preview($file)])
                                        : 'Upload a CSV to preview the asset records.';
                                }),
                        ]),
                ])
                ->action(function (Actions\Action $action, array $data): void {
                    abort_unless($this->canImportAssets(), 403);
                    $file = $data['csv'];
                    if (! $file instanceof TemporaryUploadedFile) {
                        throw ValidationException::withMessages(['csv' => 'Upload a CSV file.']);
                    }

                    try {
                        $count = app(AssetCsvImport::class)->import($file);
                    } catch (ValidationException $exception) {
                        Notification::make()->danger()->title('CSV was not imported')->body($exception->getMessage())->send();

                        $action->halt();
                    } catch (UniqueConstraintViolationException) {
                        Notification::make()->danger()->title('An asset tag already exists')->body('Review the CSV for duplicates and try again.')->send();

                        $action->halt();
                    }

                    Notification::make()->success()->title("Imported {$count} assets")->send();
                }),
        ];
    }
}
