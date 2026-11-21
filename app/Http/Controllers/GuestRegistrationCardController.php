<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Filament\Operations\Resources\GuestStayResource;
use App\Services\AssistedReservationPayments;
use Carbon\CarbonImmutable;

class GuestRegistrationCardController extends Controller
{
    public function __invoke(Booking $booking)
    {
        abort_unless(GuestStayResource::canViewAny(), 403);
        $booking->loadMissing(['user', 'room', 'checkedInBy']);
        $nights = max(1, (int) CarbonImmutable::parse($booking->check_in)->diffInDays(CarbonImmutable::parse($booking->check_out)));
        $total = AssistedReservationPayments::kobo($booking->total_price);
        $paid = $booking->paid_at ? $total : AssistedReservationPayments::verified($booking);
        $terms = $booking->reservation_terms;
        $registration = $booking->guest_registration ?? [];
        \Livewire\Livewire::flushState();
        return response()->view('registrations.guest-card', [
            'booking' => $booking, 'registration' => $registration, 'nights' => $nights,
            'nightly' => $total / $nights, 'total' => $total, 'paid' => $paid, 'balance' => max(0, $total - $paid),
            'name' => $registration['full_name'] ?? $booking->booking_contact['name'] ?? $booking->user?->name,
            'phone' => $registration['phone'] ?? $booking->booking_contact['phone'] ?? $booking->user?->phone,
            'email' => $booking->booking_contact['email'] ?? $booking->user?->email,
            'arrivalTime' => CarbonImmutable::parse($terms['check_in_at'] ?? $booking->check_in.' '.config('property.check_in_time'), config('property.timezone'))->timezone(config('property.timezone'))->format('g:i A'),
            'departureTime' => CarbonImmutable::parse($terms['check_out_at'] ?? $booking->check_out.' '.config('property.check_out_time'), config('property.timezone'))->timezone(config('property.timezone'))->format('g:i A'),
            'terms' => $terms, 'stage' => GuestStayResource::stayStage($booking),
        ])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer');
    }
}
