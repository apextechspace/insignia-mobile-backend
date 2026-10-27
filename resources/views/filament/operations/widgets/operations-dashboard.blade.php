<x-filament-widgets::widget>
    <div class="space-y-6">
        {{-- ============================================================ --}}
        {{-- EXECUTIVE BANNER: RICH INSIGNIA PLUM LUXURY GRADIENT --}}
        {{-- ============================================================ --}}
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-[#4A0E2E] via-[#5D123B] to-[#7B184F] p-6 text-white shadow-xl border border-[#8C235E]/30">
            {{-- Background decorative watermark / ambient glow --}}
            <div class="absolute -right-8 -top-8 w-64 h-64 rounded-full bg-white/5 blur-2xl pointer-events-none"></div>
            <div class="absolute right-20 -bottom-10 w-48 h-48 rounded-full bg-[#E5C07B]/10 blur-xl pointer-events-none"></div>

            <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-white/10 backdrop-blur-md text-[#E5C07B] border border-[#E5C07B]/30 tracking-wider uppercase">
                            ✨ Insignia Court · Abuja
                        </span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-500/20 text-emerald-300 border border-emerald-400/30">
                            ● Latest Snapshot
                        </span>
                    </div>
                    <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-white font-serif">
                        Operations & Facility Command
                    </h1>
                    <p class="text-xs md:text-sm text-pink-100/80 mt-1 max-w-xl">
                        Current status of the managed serviced apartments, guest turnaround workflows, and facility coordination.
                    </p>
                </div>

                <div class="flex items-center gap-3 bg-black/25 backdrop-blur-md px-4 py-3 rounded-xl border border-white/10">
                    <div class="text-right">
                        <p class="text-xs text-pink-200/70 font-medium uppercase tracking-wider">
                            {{ now()->format('l, d M Y') }}
                        </p>
                        <p class="text-lg font-bold text-white font-mono">
                            {{ now()->format('h:i A') }} <span class="text-xs text-[#E5C07B]">WAT</span>
                        </p>
                    </div>
                    <div class="h-8 w-px bg-white/15"></div>
                    <div class="text-center px-1">
                        <p class="text-[10px] text-pink-200/70 font-semibold uppercase">Shift</p>
                        <p class="text-xs font-bold text-emerald-300">
                            {{ now()->format('H') < 14 ? 'Morning' : (now()->format('H') < 21 ? 'Evening' : 'Night') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        @unless(auth()->user()?->isStaff())
        <nav class="flex flex-wrap items-center gap-2" aria-label="Operations shortcuts">
            <a href="{{ \App\Filament\Operations\Resources\GuestStayResource::getUrl('index') }}" class="inline-flex items-center gap-2 rounded-lg bg-[#4A0E2E] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#682044] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#4A0E2E]">
                Guest arrivals &amp; departures
            </a>
            <a href="{{ \App\Filament\Operations\Resources\RoomReadinessResource::getUrl('index') }}" class="inline-flex items-center gap-2 rounded-lg border border-amber-300 bg-amber-50 px-4 py-2.5 text-sm font-semibold text-amber-900 hover:bg-amber-100">
                Room readiness review
            </a>
            <a href="{{ \App\Filament\Operations\Resources\RoomStatusResource::getUrl('index') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-800 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                Room status
            </a>
        </nav>
        @endunless

        {{-- ============================================================ --}}
        {{-- KPI STATS CARDS: PLUM, GOLD, EMERALD & AMBER LUXURY ACCENTS --}}
        {{-- ============================================================ --}}
        @php
            $totalRooms = $rooms->count();
            $occupancyPercentage = $totalRooms > 0 ? round(($occupiedCount / $totalRooms) * 100) : 0;
        @endphp
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- 1. Occupancy Card --}}
            <div class="rounded-2xl p-5 bg-white dark:bg-gray-900 border border-gray-200/80 dark:border-gray-800 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Suite Occupancy</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-[#4A0E2E]/10 dark:bg-[#4A0E2E]/30 text-[#4A0E2E] dark:text-pink-300">
                        {{ $occupancyPercentage }}%
                    </span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-gray-900 dark:text-white">{{ $occupiedCount }}</span>
                    <span class="text-sm font-semibold text-gray-400">/ {{ $totalRooms }} Suites</span>
                </div>
                {{-- Progress Bar --}}
                <div class="mt-3 w-full bg-gray-100 dark:bg-gray-800 rounded-full h-1.5 overflow-hidden">
                    <div class="bg-gradient-to-r from-[#4A0E2E] to-[#8C235E] h-1.5 rounded-full" style="width: {{ $occupancyPercentage }}%"></div>
                </div>
                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-2 flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-[#4A0E2E] inline-block"></span>
                    {{ $occupiedCount }} in-stay guest reservations
                </p>
            </div>

            {{-- 2. Ready for VIP Card --}}
            <div class="rounded-2xl p-5 bg-white dark:bg-gray-900 border border-gray-200/80 dark:border-gray-800 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Ready for Check-In</span>
                    <span class="text-emerald-500">✨</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-emerald-700 dark:text-emerald-300">{{ $availableCount }}</span>
                    <span class="text-sm font-semibold text-gray-400">/ {{ $totalRooms }} Suites</span>
                </div>
                <div class="mt-3 w-full bg-gray-100 dark:bg-gray-800 rounded-full h-1.5 overflow-hidden">
                    <div class="bg-emerald-500 h-1.5 rounded-full" style="width: {{ round(($totalRooms > 0 ? ($availableCount / $totalRooms) : 0) * 100) }}%"></div>
                </div>
                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-2">
                    Inspected & sanitized for arrival
                </p>
            </div>

            {{-- 3. Today's Front Desk Activity --}}
            <div class="rounded-2xl p-5 bg-white dark:bg-gray-900 border border-gray-200/80 dark:border-gray-800 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Today's Traffic</span>
                    <span class="text-blue-500">🔄</span>
                </div>
                <div class="mt-2 flex items-baseline gap-3">
                    <div>
                        <span class="text-2xl font-extrabold text-blue-600 dark:text-blue-400">↓ {{ $todayArrivals }}</span>
                        <span class="text-[10px] uppercase text-gray-400 block font-medium">Arrivals</span>
                    </div>
                    <div class="h-8 w-px bg-gray-200 dark:bg-gray-700"></div>
                    <div>
                        <span class="text-2xl font-extrabold text-purple-600 dark:text-purple-400">↑ {{ $todayDepartures }}</span>
                        <span class="text-[10px] uppercase text-gray-400 block font-medium">Departures</span>
                    </div>
                </div>
                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-3">
                    Scheduled gate movements today
                </p>
            </div>

            {{-- 4. Open Maintenance & Tasks --}}
            <div class="rounded-2xl p-5 bg-white dark:bg-gray-900 border border-gray-200/80 dark:border-gray-800 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-amber-600 dark:text-amber-400">Pending Actions</span>
                    <span class="text-amber-500">⚠️</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-amber-600 dark:text-amber-400">{{ $openIncidents + $pendingTasks }}</span>
                    <span class="text-xs font-semibold text-gray-500">Active Items</span>
                </div>
                <div class="mt-3 flex items-center gap-3 text-xs text-gray-600 dark:text-gray-400">
                    <span class="inline-flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> {{ $openIncidents }} Incidents
                    </span>
                    <span class="inline-flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> {{ $pendingTasks }} Tasks
                    </span>
                </div>
            </div>
        </div>

        @unless(auth()->user()?->isStaff())
        {{-- Booking records are separate from physical occupied/cleaning status. --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <section class="rounded-2xl p-5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                <h2 class="font-bold text-lg text-gray-900 dark:text-white">Pending Room Bookings ({{ $pendingReservations->count() }})</h2>
                <p class="text-xs text-gray-500 mt-1">Received bookings awaiting payment verification; not confirmed stays.</p>
                <div class="mt-4 space-y-3">
                    @forelse($pendingReservations as $booking)
                        <div class="flex items-center justify-between gap-3 border-b border-gray-100 dark:border-gray-800 pb-2">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">#{{ $booking->id }} · {{ $booking->room?->name ?? 'Suite' }}</p>
                                <p class="text-xs text-gray-500">{{ $booking->user?->name ?? 'Guest' }} · {{ $booking->check_in }} to {{ $booking->check_out }}</p>
                            </div>
                            <span class="text-xs text-amber-600 font-semibold whitespace-nowrap">Pending review</span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No pending room bookings.</p>
                    @endforelse
                </div>
            </section>
            <section class="rounded-2xl p-5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                <h2 class="font-bold text-lg text-gray-900 dark:text-white">Upcoming Confirmed Stays ({{ $upcomingReservations->count() }})</h2>
                <p class="text-xs text-gray-500 mt-1">Paid reservations that require upcoming front-desk preparation.</p>
                <div class="mt-4 space-y-3">
                    @forelse($upcomingReservations as $booking)
                        <div class="flex items-center justify-between gap-3 border-b border-gray-100 dark:border-gray-800 pb-2">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">#{{ $booking->id }} · {{ $booking->room?->name ?? 'Suite' }}</p>
                                <p class="text-xs text-gray-500">{{ $booking->user?->name ?? 'Guest' }} · {{ $booking->check_in }} to {{ $booking->check_out }}</p>
                            </div>
                            <span class="text-xs text-emerald-600 font-semibold whitespace-nowrap">Confirmed</span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No upcoming confirmed stays.</p>
                    @endforelse
                </div>
            </section>
        </div>

        @endunless
        {{-- ============================================================ --}}
        {{-- DYNAMIC APARTMENT STATUS GRID --}}
        {{-- ============================================================ --}}
        <div class="bg-white dark:bg-gray-900 rounded-2xl p-6 border border-gray-200/80 dark:border-gray-800 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-6">
                <div>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <span>🏛️ Serviced Suites Grid</span>

                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        Room-by-room operational status from the latest dashboard request
                    </p>
                </div>

                {{-- Status Legend Pills --}}
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="inline-flex items-center gap-1.5 text-[11px] font-medium px-2.5 py-1 rounded-full bg-red-50 dark:bg-red-950/40 text-red-700 dark:text-red-300 border border-red-200 dark:border-red-900/50">
                        <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span> Occupied
                    </span>
                    <span class="inline-flex items-center gap-1.5 text-[11px] font-medium px-2.5 py-1 rounded-full bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-900/50">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Ready
                    </span>
                    <span class="inline-flex items-center gap-1.5 text-[11px] font-medium px-2.5 py-1 rounded-full bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-900/50">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Cleaning
                    </span>
                    <span class="inline-flex items-center gap-1.5 text-[11px] font-medium px-2.5 py-1 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700">
                        <span class="w-1.5 h-1.5 rounded-full bg-gray-500"></span> Maintenance
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach($rooms as $index => $room)
                    @php
                        $config = match($room->status) {
                            'occupied' => [
                                'cardBg'    => 'bg-gradient-to-br from-red-50/60 to-white dark:from-red-950/20 dark:to-gray-900',
                                'border'    => 'border-red-200 dark:border-red-900/60 hover:border-red-400',
                                'badgeBg'   => 'bg-red-100 text-red-800 dark:bg-red-900/60 dark:text-red-200 border border-red-200 dark:border-red-800',
                                'dot'       => 'bg-red-500 animate-pulse',
                                'label'     => 'Occupied',
                                'actionBg'  => 'text-red-700 dark:text-red-300 bg-red-50 dark:bg-red-900/30 hover:bg-red-100',
                            ],
                            'available' => [
                                'cardBg'    => 'bg-gradient-to-br from-emerald-50/60 to-white dark:from-emerald-950/20 dark:to-gray-900',
                                'border'    => 'border-emerald-200 dark:border-emerald-900/60 hover:border-emerald-400',
                                'badgeBg'   => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200 border border-emerald-200 dark:border-emerald-800',
                                'dot'       => 'bg-emerald-500',
                                'label'     => 'Ready for Guest',
                                'actionBg'  => 'text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-900/30 hover:bg-emerald-100',
                            ],
                            'cleaning' => [
                                'cardBg'    => 'bg-gradient-to-br from-amber-50/60 to-white dark:from-amber-950/20 dark:to-gray-900',
                                'border'    => 'border-amber-200 dark:border-amber-900/60 hover:border-amber-400',
                                'badgeBg'   => 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200 border border-amber-200 dark:border-amber-800',
                                'dot'       => 'bg-amber-500 animate-bounce',
                                'label'     => 'Housekeeping Active',
                                'actionBg'  => 'text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/30 hover:bg-amber-100',
                            ],
                            'maintenance' => [
                                'cardBg'    => 'bg-gradient-to-br from-gray-50 to-white dark:from-gray-800/30 dark:to-gray-900',
                                'border'    => 'border-gray-200 dark:border-gray-700 hover:border-gray-400',
                                'badgeBg'   => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200 border border-gray-300 dark:border-gray-600',
                                'dot'       => 'bg-gray-500',
                                'label'     => 'Under Maintenance',
                                'actionBg'  => 'text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200',
                            ],
                            default => [
                                'cardBg'    => 'bg-white dark:bg-gray-900',
                                'border'    => 'border-gray-200 dark:border-gray-800',
                                'badgeBg'   => 'bg-gray-100 text-gray-700',
                                'dot'       => 'bg-gray-400',
                                'label'     => ucfirst($room->status),
                                'actionBg'  => 'text-gray-700 bg-gray-50',
                            ],
                        };
                    @endphp

                    <div class="rounded-xl border {{ $config['border'] }} {{ $config['cardBg'] }} p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md flex flex-col justify-between">
                        <div>
                            {{-- Top Header Row: Suite Number and Status Pill --}}
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[11px] font-mono font-bold tracking-wider uppercase text-gray-400 dark:text-gray-500">
                                    Suite {{ $index + 1 }}
                                </span>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $config['badgeBg'] }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $config['dot'] }}"></span>
                                    {{ $config['label'] }}
                                </span>
                            </div>

                            {{-- Suite Name --}}
                            <h3 class="text-xl font-extrabold text-gray-900 dark:text-white tracking-tight">
                                {{ $room->name }}
                            </h3>

                            {{-- Dynamic Suite Details / Guest Information --}}
                            @if($room->status === 'occupied' && $room->activeBooking?->user && !auth()->user()?->isStaff())
                                <div class="mt-3 p-3 rounded-lg bg-white/80 dark:bg-gray-800/80 border border-red-100 dark:border-red-900/30">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-full bg-[#4A0E2E]/10 dark:bg-[#4A0E2E]/30 flex items-center justify-center text-xs font-bold text-[#4A0E2E] dark:text-pink-300">
                                            {{ strtoupper(substr($room->activeBooking->user->name, 0, 1)) }}
                                        </div>
                                        <div class="truncate">
                                            <p class="text-xs font-bold text-gray-900 dark:text-gray-100 truncate">
                                                {{ $room->activeBooking->user->name }}
                                            </p>
                                            <p class="text-[11px] text-gray-500 dark:text-gray-400">
                                                Checkout: {{ \Carbon\Carbon::parse($room->activeBooking->check_out)->format('d M') }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            @elseif($room->status === 'cleaning')
                                <div class="mt-3 p-3 rounded-lg bg-amber-50/80 dark:bg-amber-950/30 border border-amber-200/60 dark:border-amber-900/40">
                                    <p class="text-xs font-semibold text-amber-800 dark:text-amber-300 flex items-center gap-1.5">
                                        <span>🧹</span> Turnover in progress
                                    </p>
                                    <p class="text-[11px] text-amber-700/80 dark:text-amber-400 mt-0.5">
                                        {{ $room->housekeeping_notes ?: 'Awaiting cleaning checklist completion and supervisor verification.' }}
                                    </p>
                                </div>
                            @elseif($room->status === 'maintenance')
                                <div class="mt-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700">
                                    <p class="text-xs font-semibold text-gray-800 dark:text-gray-300 flex items-center gap-1.5">
                                        <span>🔧</span> Scheduled repair
                                    </p>
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                                        {{ $room->housekeeping_notes ?: 'Room blocked while maintenance is being assessed.' }}
                                    </p>
                                </div>
                            @else
                                <div class="mt-3 p-3 rounded-lg bg-emerald-50/60 dark:bg-emerald-950/30 border border-emerald-100 dark:border-emerald-900/30">
                                    <p class="text-xs font-semibold text-emerald-800 dark:text-emerald-300 flex items-center gap-1.5">
                                        <span>✨</span> Available for arrivals
                                    </p>
                                    <p class="text-[11px] text-emerald-700/80 dark:text-emerald-400 mt-0.5">
                                        Room is currently marked ready for guest check-in
                                    </p>
                                </div>
                            @endif
                        </div>

                        {{-- Footer Quick Actions --}}
                        <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-800/80 flex items-center justify-between">
                            <span class="text-[11px] text-gray-400 dark:text-gray-500">
                                {{ $room->housekeeping_notes ? \Illuminate\Support\Str::limit($room->housekeeping_notes, 24) : 'No room notes' }}
                            </span>
                            @if(\App\Filament\Operations\Resources\RoomStatusResource::canEdit($room))
                            <a href="{{ \App\Filament\Operations\Resources\RoomStatusResource::getUrl('edit', ['record' => $room]) }}"
                               class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-md {{ $config['actionBg'] }} transition-colors">
                                Update Status →
                            </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
