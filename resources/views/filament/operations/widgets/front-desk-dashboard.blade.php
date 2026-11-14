@php
    $stayResource = \App\Filament\Operations\Resources\GuestStayResource::class;
    $propertyDate = $propertyNow->toDateString();
    $departures = $inHouse->filter(fn ($stay) => $stay->check_out <= $propertyDate);
@endphp

<div class="space-y-6">
    <p>{{ $propertyNow->format('l, j M Y · g:i A T') }}</p>
    <x-filament::button tag="a" :href="\App\Filament\Operations\Resources\RoomStatusResource::getUrl(panel: 'operations')">
        Open Room Status
    </x-filament::button>
    @foreach ([
        ['Today’s arrivals', $arrivals, 'arrivals_today'],
        ['Departures due', $departures, 'departures_due'],
        ['In-house guests', $inHouse, 'in_house'],
    ] as [$heading, $stays, $filter])
        <x-filament::section>
            <x-slot name="heading">{{ $heading }} ({{ $stays->count() }})</x-slot>
            <ul class="space-y-3">
                @forelse ($stays->take(6) as $stay)
                    <li>
                        <a class="font-semibold text-primary-600" href="{{ $stayResource::getUrl('view', ['record' => $stay], panel: 'operations') }}">
                            {{ $stay->booking_contact['name'] ?? $stay->user?->name ?? 'Guest' }} · {{ $stay->room?->name ?? 'Room unassigned' }}
                        </a>
                        <p>
                            @if ($filter === 'arrivals_today')
                                {{ $stay->paid_at ? 'Payment verified' : 'Payment awaiting verification' }}
                                · {{ match ($stay->room?->status) {
                                    'available' => 'Room ready',
                                    'cleaning' => 'Room still being cleaned',
                                    'maintenance' => 'Room under maintenance',
                                    default => 'Confirm room readiness',
                                } }}
                            @else
                                Checkout {{ $stay->check_out }}{{ $stay->check_out < $propertyDate ? ' · Overdue' : '' }}
                            @endif
                        </p>
                    </li>
                @empty
                    <li>No stays in this queue.</li>
                @endforelse
            </ul>
            <div class="mt-4">
                <x-filament::button tag="a" :href="$stayResource::getUrl('index', ['tableFilters' => [$filter => ['isActive' => true]]], panel: 'operations')">
                    Open queue
                </x-filament::button>
            </div>
        </x-filament::section>
    @endforeach

    <x-filament::section>
        <x-slot name="heading">Needs Attention</x-slot>
        <p class="font-semibold">Transfers awaiting verification ({{ $paymentReviews->count() }})</p>
        <ul class="space-y-2">
            @forelse ($paymentReviews->take(6) as $stay)
                <li>
                    <a class="text-primary-600" href="{{ $stayResource::getUrl('view', ['record' => $stay], panel: 'operations') }}">
                        Booking #{{ $stay->id }} · {{ $stay->room?->name ?? 'Room unassigned' }}
                    </a>
                </li>
            @empty
                <li>No transfers awaiting review for arrivals due.</li>
            @endforelse
        </ul>
        <p class="mt-4 font-semibold">Recent open incidents and guest follow-ups</p>
        <ul class="space-y-2">
            @forelse ($openIssues as $issue)
                <li>
                    <a class="text-primary-600" href="{{ \App\Filament\Operations\Resources\IncidentResource::getUrl('edit', ['record' => $issue], panel: 'operations') }}">{{ $issue->title }}</a>
                    · {{ str($issue->status)->headline() }} · {{ $issue->assignedTo?->name ?? 'Unassigned' }}
                </li>
            @empty
                <li>No open issues.</li>
            @endforelse
        </ul>
        <p class="mt-4 font-semibold">Recent front desk handovers</p>
        <ul class="space-y-2">
            @forelse ($handoverShifts as $shift)
                <li class="whitespace-pre-line">{{ $shift->shift_date->format('j M') }} · {{ $shift->user?->name }} · {{ $shift->handover_notes }}</li>
            @empty
                <li>No handover notes recorded.</li>
            @endforelse
        </ul>
        <div class="mt-4">
            <x-filament::button tag="a" :href="\App\Filament\Operations\Resources\StaffShiftResource::getUrl(panel: 'operations')">
                Open Duty Roster
            </x-filament::button>
        </div>
    </x-filament::section>
</div>
