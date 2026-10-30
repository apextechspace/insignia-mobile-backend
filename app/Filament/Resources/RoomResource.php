<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoomResource\Pages;
use App\Filament\Resources\RoomResource\Widgets;
use App\Models\Room;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class RoomResource extends Resource
{
    protected static ?string $model = Room::class;

    protected static ?string $navigationIcon = 'heroicon-o-home-modern';

    protected static ?string $navigationLabel = 'Suites & Rooms';

    protected static ?string $modelLabel = 'Suite';

    protected static ?string $pluralModelLabel = 'Suites & Rooms';

    protected static ?string $navigationGroup = 'Hospitality';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->columns(['default' => 1, 'lg' => 3])
            ->schema([
                // Main Workspace: 2 Columns on Desktop
                Forms\Components\Group::make([
                    // Section A: Suite Identity & Description
                    Forms\Components\Section::make('Suite Identity & Story')
                        ->extraAttributes(['class' => 'insignia-room-section'])
                        ->description('Core branding, naming, categorization, and guest reservation write-up.')
                        ->icon('heroicon-m-building-office-2')
                        ->schema([
                            Forms\Components\Grid::make(2)->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Suite / Room Name')
                                    ->placeholder('e.g. Atala Penthouse Suite, Papilio Executive')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(['default' => 2, 'md' => 1]),
                                Forms\Components\Select::make('type')
                                    ->label('Category Tier')
                                    ->options([
                                        'Standard Room' => 'Standard Room',
                                        'Executive Room' => 'Executive Room',
                                        'Suite' => 'Suite',
                                    ])
                                    ->required()
                                    ->native(false)
                                    ->columnSpan(['default' => 2, 'md' => 1]),
                            ]),
                            Forms\Components\Grid::make(2)->schema([
                                Forms\Components\TextInput::make('tag')
                                    ->label('Marketing Highlight Tag')
                                    ->placeholder('e.g. VIP Favourite, Best Value, Panoramic Balcony')
                                    ->maxLength(100)
                                    ->helperText('Short badge featured on guest suite cards and search filters.')
                                    ->columnSpan(['default' => 2, 'md' => 1]),
                                Forms\Components\TextInput::make('category_description')
                                    ->label('Category Positioning Summary')
                                    ->placeholder('e.g. Luxury single-bedroom suite with curated executive lounge access')
                                    ->maxLength(255)
                                    ->helperText('Concise one-line summary used in card headers.')
                                    ->columnSpan(['default' => 2, 'md' => 1]),
                            ]),
                            Forms\Components\Textarea::make('description')
                                ->label('Public Room Narrative')
                                ->rows(4)
                                ->placeholder('Describe the architectural design, interior furnishings, floor plan, views, bathroom finishes, and unique guest experience...')
                                ->helperText('Featured on online reservation portals, mobile applications, and booking confirmations.')
                                ->columnSpanFull(),
                        ]),

                    // Section C: Amenities & Premium Inclusions
                    Forms\Components\Section::make('Amenities & Premium Inclusions')
                        ->extraAttributes(['class' => 'insignia-room-section'])
                        ->description('Select all features, services, and conveniences included in this suite rate.')
                        ->icon('heroicon-m-sparkles')
                        ->collapsible()
                        ->schema([
                            Forms\Components\CheckboxList::make('amenities')
                                ->label('')
                                ->options([
                                    'wifi' => 'High-Speed WiFi (Fibre)',
                                    'tv' => 'Smart TV & Streaming Apps',
                                    'pool' => 'Private / Pool Access',
                                    'person' => 'Dedicated Butler Service',
                                    'wine' => 'Curated Mini-bar & Wine',
                                    'sparkles' => 'Spa & Wellness Access',
                                    'restaurant' => 'Complimentary Breakfast',
                                    'fitness' => 'Gym & Fitness Centre',
                                    'snow' => 'Climate Control (AC)',
                                    'car' => 'Secure On-site Parking',
                                    'speedometer' => 'Gigabit Fibre Internet',
                                ])
                                ->columns(['default' => 1, 'sm' => 2, 'md' => 3])
                                ->bulkToggleable()
                                ->helperText('Check all amenities that apply. Guests can filter by these options in the booking catalogue.'),
                        ]),

                    // Section D: Photography Gallery
                    Forms\Components\Section::make('Photography Gallery')
                        ->extraAttributes(['class' => 'insignia-room-section'])
                        ->description('High-resolution imagery for guest catalogues. The first photo serves as the primary cover image.')
                        ->icon('heroicon-m-photo')
                        ->collapsible()
                        ->schema([
                            Forms\Components\FileUpload::make('images')
                                ->label('Suite Photos')
                                ->multiple()
                                ->reorderable()
                                ->panelLayout('grid')
                                ->disk('public')
                                ->directory('rooms')
                                ->image()
                                ->maxSize(10240)
                                ->openable()
                                ->downloadable()
                                ->previewable()
                                ->hint('Primary cover is 1st photo')
                                ->hintIcon('heroicon-m-star')
                                ->appendFiles()
                                ->columnSpanFull()
                                ->helperText('Upload JPG, PNG, or WebP photos (up to 10MB each). Drag thumbnails to reorder. The first photo is automatically used as the featured cover image on the booking portal.'),
                        ]),

                    // Section E: Video Walkthrough
                    Forms\Components\Section::make('Cinematic Video Walkthrough')
                        ->extraAttributes(['class' => 'insignia-room-section'])
                        ->description('Provide an optional high-definition video walkthrough of the suite.')
                        ->icon('heroicon-m-film')
                        ->collapsible()
                        ->collapsed(fn (?Room $record) => empty($record?->video_url))
                        ->schema([
                            Forms\Components\FileUpload::make('video_url')
                                ->label('Walkthrough Video File')
                                ->disk('public')
                                ->directory('rooms')
                                ->acceptedFileTypes(['video/mp4', 'video/webm', 'video/quicktime'])
                                ->maxSize(40960)
                                ->openable()
                                ->downloadable()
                                ->previewable()
                                ->hint('Max 40MB · MP4, WebM, MOV')
                                ->hintIcon('heroicon-m-video-camera')
                                ->columnSpanFull()
                                ->helperText('Upload an MP4, WebM, or QuickTime video up to 40MB. Click the open icon to preview the video in a new browser tab. Existing videos remain intact unless replaced.'),
                        ]),
                ])->columnSpan(['default' => 1, 'lg' => 2]),

                // Sidebar Column: 1 Column on Desktop
                Forms\Components\Group::make([
                    // Section B1: Pricing & Commercial Terms
                    Forms\Components\Section::make('Pricing & Commercial Terms')
                        ->extraAttributes(['class' => 'insignia-room-section'])
                        ->description('Nightly guest rates and booking visibility.')
                        ->icon('heroicon-m-banknotes')
                        ->schema([
                            Forms\Components\TextInput::make('price_per_night')
                                ->label('Nightly Rate (NGN)')
                                ->required()
                                ->numeric()
                                ->minValue(0)
                                ->step(0.01)
                                ->prefix('₦')
                                ->placeholder('150000')
                                ->helperText('Standard rack rate per night in Nigerian Naira.'),
                            Forms\Components\Toggle::make('is_available')
                                ->label('Published on Booking Engine')
                                ->helperText('When enabled, guests can view and reserve this suite on the web portal and mobile app.')
                                ->onColor('success')
                                ->offColor('danger')
                                ->default(true)
                                ->required(),
                        ]),

                    // Section B2: Operations & Housekeeping
                    Forms\Components\Section::make('Operations & Housekeeping')
                        ->extraAttributes(['class' => 'insignia-room-section'])
                        ->description('Real-time operational readiness and shift handover notes.')
                        ->icon('heroicon-m-clipboard-document-check')
                        ->schema([
                            Forms\Components\Select::make('status')
                                ->label('Housekeeping State')
                                ->options([
                                    'available'   => 'Ready for Guest (Available)',
                                    'occupied'    => 'Currently Occupied',
                                    'cleaning'    => 'Turnover / Cleaning in Progress',
                                    'maintenance' => 'Under Maintenance',
                                    'reserved'    => 'Reserved / Blocked',
                                ])
                                ->default('available')
                                ->required()
                                ->native(false)
                                ->helperText('Releasing from Cleaning to Ready requires a verified supervisor turnaround task.'),
                            Forms\Components\Textarea::make('housekeeping_notes')
                                ->label('Turnover & Shift Notes')
                                ->rows(3)
                                ->placeholder('e.g. Master bath deep-cleaned, hypoallergenic pillows placed, linen refreshed.')
                                ->helperText('Internal shift notes shared between housekeeping supervisors and front desk.'),
                        ]),

                    // Section B3: Suite Quick Snapshot (on Edit)
                    Forms\Components\Section::make('Suite Snapshot')
                        ->extraAttributes(['class' => 'insignia-room-section'])
                        ->icon('heroicon-m-information-circle')
                        ->schema([
                            Forms\Components\Placeholder::make('created_at')
                                ->label('Registered On')
                                ->content(fn (?Room $record): string => $record?->created_at ? $record->created_at->format('M d, Y · H:i') : 'New Record'),
                            Forms\Components\Placeholder::make('updated_at')
                                ->label('Last Modified')
                                ->content(fn (?Room $record): string => $record?->updated_at ? $record->updated_at->diffForHumans() : '—'),
                            Forms\Components\Placeholder::make('active_booking_info')
                                ->label('Active Guest Stay')
                                ->content(function (?Room $record): string {
                                    if (!$record) return '—';
                                    $active = $record->activeBooking;
                                    if (!$active) return 'No active stay';
                                    $guestName = $active->user?->name ?? 'Registered Guest';
                                    return "Guest: {$guestName} (Check-in: {$active->check_in})";
                                })
                                ->visible(fn (?Room $record) => $record && $record->status === 'occupied'),
                        ])
                        ->collapsible()
                        ->hidden(fn (?Room $record) => $record === null),
                ])->columnSpan(['default' => 1, 'lg' => 1]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Suite Visuals & Media')
                    ->icon('heroicon-m-photo')
                    ->schema([
                        Infolists\Components\ImageEntry::make('images')
                            ->label('Gallery Photos')
                            ->disk('public')
                            ->height(160)
                            ->extraImgAttributes([
                                'class' => 'rounded-xl object-cover ring-1 ring-white/10 shadow-md',
                            ])
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('video_url')
                            ->label('Video Walkthrough')
                            ->formatStateUsing(fn ($state) => $state ? 'Play / Stream Walkthrough Video' : 'No video uploaded')
                            ->url(fn ($state) => $state ? asset('storage/' . $state) : null, shouldOpenInNewTab: true)
                            ->icon(fn ($state) => $state ? 'heroicon-m-film' : 'heroicon-m-video-camera-slash')
                            ->color(fn ($state) => $state ? 'primary' : 'gray')
                            ->visible(fn (Room $record) => !empty($record->video_url)),
                    ]),

                Infolists\Components\Section::make('Suite Specifications')
                    ->icon('heroicon-m-information-circle')
                    ->schema([
                        Infolists\Components\Grid::make(3)->schema([
                            Infolists\Components\TextEntry::make('name')
                                ->label('Suite Name')
                                ->weight('bold')
                                ->size('lg'),
                            Infolists\Components\TextEntry::make('type')
                                ->label('Category')
                                ->badge()
                                ->color('info'),
                            Infolists\Components\TextEntry::make('tag')
                                ->label('Marketing Tag')
                                ->placeholder('—')
                                ->badge()
                                ->color('primary'),
                        ]),
                        Infolists\Components\Grid::make(3)->schema([
                            Infolists\Components\TextEntry::make('price_per_night')
                                ->label('Nightly Rate')
                                ->money('NGN')
                                ->weight('bold')
                                ->color('success'),
                            Infolists\Components\TextEntry::make('status')
                                ->label('Housekeeping State')
                                ->badge()
                                ->colors([
                                    'success' => 'available',
                                    'danger'  => 'occupied',
                                    'warning' => 'cleaning',
                                    'gray'    => 'maintenance',
                                    'info'    => 'reserved',
                                ])
                                ->formatStateUsing(fn (string $state): string => match ($state) {
                                    'available'   => 'Ready for Guest',
                                    'occupied'    => 'Currently Occupied',
                                    'cleaning'    => 'Cleaning in Progress',
                                    'maintenance' => 'Under Maintenance',
                                    'reserved'    => 'Reserved',
                                    default       => ucfirst($state),
                                }),
                            Infolists\Components\IconEntry::make('is_available')
                                ->label('Online Booking')
                                ->boolean(),
                        ]),
                        Infolists\Components\TextEntry::make('description')
                            ->label('Public Description')
                            ->placeholder('No public description provided.')
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('Amenities & Premium Inclusions')
                    ->icon('heroicon-m-sparkles')
                    ->schema([
                        Infolists\Components\TextEntry::make('amenities')
                            ->label('')
                            ->badge()
                            ->color('primary')
                            ->separator(',')
                            ->formatStateUsing(fn ($state) => match ($state) {
                                'wifi' => 'High-Speed WiFi',
                                'tv' => 'Smart TV & Streaming',
                                'pool' => 'Private Pool Access',
                                'person' => 'Executive Butler Service',
                                'wine' => 'Curated Mini-bar & Wine',
                                'sparkles' => 'Spa & Wellness Access',
                                'restaurant' => 'Breakfast Included',
                                'fitness' => 'Gym & Fitness Centre',
                                'snow' => 'Climate Control (AC)',
                                'car' => 'Secure Parking',
                                'speedometer' => 'Gigabit Fibre Internet',
                                default => ucfirst($state),
                            })
                            ->columnSpanFull(),
                    ])->collapsible(),

                Infolists\Components\Section::make('Housekeeping & Operational Status')
                    ->icon('heroicon-m-clipboard-document-check')
                    ->schema([
                        Infolists\Components\TextEntry::make('housekeeping_notes')
                            ->label('Turnover / Shift Notes')
                            ->placeholder('No current notes logged for this suite.')
                            ->columnSpanFull(),
                    ])->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('images')
                    ->label('Photo')
                    ->disk('public')
                    ->circular(false)
                    ->width(120)
                    ->height(90)
                    ->extraImgAttributes([
                        'class' => 'rounded-xl object-cover ring-1 ring-slate-200 dark:ring-white/10 shadow-sm transition hover:scale-105',
                        'loading' => 'lazy',
                    ])
                    ->defaultImageUrl(asset('images/insignia-court-logo.svg'))
                    ->stacked()
                    ->limit(1),

                Tables\Columns\TextColumn::make('name')
                    ->label('Suite & Category')
                    ->searchable(['name', 'tag', 'type'])
                    ->sortable()
                    ->weight('bold')
                    ->size('lg')
                    ->description(function (Room $record): string {
                        $parts = [];
                        if ($record->tag) {
                            $parts[] = "★ {$record->tag}";
                        }
                        $parts[] = $record->type;
                        return implode(' · ', $parts);
                    }),

                Tables\Columns\TextColumn::make('price_per_night')
                    ->label('Nightly Rate')
                    ->money('NGN')
                    ->sortable()
                    ->weight('bold')
                    ->fontFamily('mono')
                    ->size('base')
                    ->description(fn () => 'per night (tax incl.)'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Housekeeping State')
                    ->badge()
                    ->colors([
                        'success' => 'available',
                        'danger'  => 'occupied',
                        'warning' => 'cleaning',
                        'gray'    => 'maintenance',
                        'info'    => 'reserved',
                    ])
                    ->icons([
                        'heroicon-m-check-badge' => 'available',
                        'heroicon-m-user' => 'occupied',
                        'heroicon-m-sparkles' => 'cleaning',
                        'heroicon-m-wrench-screwdriver' => 'maintenance',
                        'heroicon-m-calendar-days' => 'reserved',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'available'   => 'Ready',
                        'occupied'    => 'Occupied',
                        'cleaning'    => 'Cleaning',
                        'maintenance' => 'Maintenance',
                        'reserved'    => 'Reserved',
                        default       => ucfirst($state),
                    })
                    ->description(function (Room $record): ?string {
                        if ($record->status === 'occupied' && $record->activeBooking) {
                            $guest = $record->activeBooking->user?->name ?? 'Checked In';
                            $checkOut = $record->activeBooking->check_out ? date('d M', strtotime($record->activeBooking->check_out)) : '';
                            return "Guest: {$guest}" . ($checkOut ? " · Departs {$checkOut}" : '');
                        }
                        if ($record->housekeeping_notes) {
                            return Str::limit($record->housekeeping_notes, 35);
                        }
                        if ($record->status === 'available') {
                            return 'Turnkey ready for guest';
                        }
                        return null;
                    }),

                Tables\Columns\ToggleColumn::make('is_available')
                    ->label('Listed Online')
                    ->tooltip('Toggle whether this suite is published and bookable by guests on mobile app and online.')
                    ->sortable(),

                Tables\Columns\TextColumn::make('amenities')
                    ->label('Amenities')
                    ->badge()
                    ->color('primary')
                    ->separator(',')
                    ->limitList(3)
                    ->expandableLimitedList()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'wifi' => 'WiFi',
                        'tv' => 'Smart TV',
                        'pool' => 'Pool',
                        'person' => 'Butler',
                        'wine' => 'Mini-bar',
                        'sparkles' => 'Spa',
                        'restaurant' => 'Breakfast',
                        'fitness' => 'Gym',
                        'snow' => 'AC',
                        'car' => 'Parking',
                        'speedometer' => 'Fibre',
                        default => ucfirst($state),
                    })
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('media_summary')
                    ->label('Media Assets')
                    ->state(function (Room $record): string {
                        $photos = count($record->images ?? []);
                        $hasVideo = !empty($record->video_url);
                        return "{$photos} " . ($photos === 1 ? 'photo' : 'photos') . ($hasVideo ? ' · HD Video' : '');
                    })
                    ->badge()
                    ->color(fn (Room $record) => !empty($record->video_url) ? 'success' : 'gray')
                    ->icon(fn (Room $record) => !empty($record->video_url) ? 'heroicon-m-video-camera' : 'heroicon-m-photo')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Modified')
                    ->dateTime('d M Y, h:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Suite Category')
                    ->options([
                        'Standard Room' => 'Standard Room',
                        'Executive Room' => 'Executive Room',
                        'Suite' => 'Suite',
                    ])
                    ->native(false),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Housekeeping State')
                    ->options([
                        'available' => 'Ready / Available',
                        'occupied' => 'Occupied',
                        'cleaning' => 'Cleaning In Progress',
                        'maintenance' => 'Under Maintenance',
                        'reserved' => 'Reserved',
                    ])
                    ->native(false),

                Tables\Filters\TernaryFilter::make('is_available')
                    ->label('Online Booking Status')
                    ->trueLabel('Listed Online')
                    ->falseLabel('Delisted / Hidden')
                    ->native(false),

                Tables\Filters\Filter::make('price_range')
                    ->form([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('price_from')
                                ->label('Min Price (₦)')
                                ->numeric()
                                ->prefix('₦'),
                            Forms\Components\TextInput::make('price_to')
                                ->label('Max Price (₦)')
                                ->numeric()
                                ->prefix('₦'),
                        ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['price_from'],
                                fn (Builder $query, $price): Builder => $query->where('price_per_night', '>=', $price),
                            )
                            ->when(
                                $data['price_to'],
                                fn (Builder $query, $price): Builder => $query->where('price_per_night', '<=', $price),
                            );
                    }),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('View')
                    ->slideOver()
                    ->icon('heroicon-m-eye')
                    ->color('gray'),

                Tables\Actions\EditAction::make()
                    ->label('Edit')
                    ->icon('heroicon-m-pencil-square')
                    ->color('primary'),

                Tables\Actions\Action::make('manageMedia')
                    ->label('Media')
                    ->icon('heroicon-m-photo')
                    ->color('info')
                    ->modalHeading(fn (Room $record) => "Manage Media · {$record->name}")
                    ->modalDescription('Upload, reorder, or replace suite photography and promotional video walkthrough.')
                    ->modalWidth('2xl')
                    ->fillForm(fn (Room $record): array => [
                        'images' => $record->images,
                        'video_url' => $record->video_url,
                    ])
                    ->form([
                        Forms\Components\FileUpload::make('images')
                            ->label('Gallery Photos')
                            ->multiple()
                            ->reorderable()
                            ->disk('public')
                            ->directory('rooms')
                            ->image()
                            ->maxSize(10240)
                            ->openable()
                            ->downloadable()
                            ->previewable()
                            ->columnSpanFull()
                            ->helperText('Upload JPG, PNG, or WebP room photos (up to 10MB each). Existing photos can be dragged to reorder.'),
                        Forms\Components\FileUpload::make('video_url')
                            ->label('Room Video Walkthrough')
                            ->disk('public')
                            ->directory('rooms')
                            ->acceptedFileTypes(['video/mp4', 'video/webm', 'video/quicktime'])
                            ->maxSize(40960)
                            ->openable()
                            ->downloadable()
                            ->previewable()
                            ->columnSpanFull()
                            ->helperText('Upload an MP4, WebM, or QuickTime video up to 40MB.'),
                    ])
                    ->action(function (Room $record, array $data): void {
                        $record->update([
                            'images' => $data['images'] ?? [],
                            'video_url' => $data['video_url'] ?? null,
                        ]);
                        Notification::make()
                            ->title('Media Assets Saved')
                            ->body("Updated media assets for {$record->name}.")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('quickStatus')
                    ->label('Status')
                    ->icon('heroicon-m-arrow-path')
                    ->color('warning')
                    ->modalHeading(fn (Room $record) => "Update Housekeeping Status · {$record->name}")
                    ->modalWidth('lg')
                    ->fillForm(fn (Room $record): array => [
                        'status' => $record->status,
                        'housekeeping_notes' => $record->housekeeping_notes,
                    ])
                    ->form([
                        Forms\Components\Select::make('status')
                            ->label('Physical Status')
                            ->options([
                                'available' => 'Ready / Available',
                                'cleaning' => 'Cleaning In Progress',
                                'maintenance' => 'Under Maintenance',
                                'reserved' => 'Reserved',
                            ])
                            ->required()
                            ->native(false)
                            ->helperText('Occupied is managed by guest check-in/checkout workflows.'),
                        Forms\Components\Textarea::make('housekeeping_notes')
                            ->label('Shift Notes')
                            ->rows(3)
                            ->placeholder('Turnover observations, maintenance requirements, or supervisor release notes...'),
                    ])
                    ->action(function (Room $record, array $data): void {
                        $record->update([
                            'status' => $data['status'],
                            'housekeeping_notes' => $data['housekeeping_notes'] ?? null,
                        ]);
                        Notification::make()
                            ->title('Status Updated')
                            ->body("{$record->name} is now marked as " . ucfirst($data['status']) . ".")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\ActionGroup::make([
                    Tables\Actions\DeleteAction::make(),
                ])
                ->icon('heroicon-m-ellipsis-vertical')
                ->color('gray')
                ->tooltip('More actions'),
            ])
            ->emptyStateHeading('No Luxury Suites Found')
            ->emptyStateDescription('No rooms match your search or active filter settings. Clear your filters or create a new suite.')
            ->emptyStateIcon('heroicon-o-home-modern')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add New Suite')
                    ->icon('heroicon-m-plus-circle'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getWidgets(): array
    {
        return [
            Widgets\RoomOverviewWidget::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRooms::route('/'),
            'create' => Pages\CreateRoom::route('/create'),
            'edit' => Pages\EditRoom::route('/{record}/edit'),
        ];
    }
}
