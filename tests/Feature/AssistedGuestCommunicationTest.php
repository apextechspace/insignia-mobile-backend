<?php

namespace Tests\Feature;

use App\Models\{Booking, Room, User};
use App\Notifications\CustomerPaymentEmail;
use App\Services\{AssistedReservationPayments, ReservationGuestCommunication};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Mail, Notification};
use Tests\TestCase;

class AssistedGuestCommunicationTest extends TestCase
{
    use RefreshDatabase;

    private function stay(?string $email = 'guest@example.test'): Booking
    {
        $staff = User::factory()->create(['role' => User::ROLE_DELIVERY_PARTNER]);
        $room = Room::create(['name' => 'Evania', 'type' => 'Executive Room', 'price_per_night' => 120937.50, 'status' => 'available']);
        return Booking::create(['reserved_by' => $staff->id, 'room_id' => $room->id, 'check_in' => today()->addDay()->toDateString(), 'check_out' => today()->addDays(2)->toDateString(), 'total_price' => 120937.50, 'minimum_payment_kobo' => 6046875, 'status' => 'pending', 'hold_expires_at' => now()->addMinutes(15), 'booking_contact' => ['name' => '<script>Guest</script>', 'phone' => '09012345678', 'email' => $email]]);
    }

    public function test_dashboard_counts_verified_deposit_and_balance_once_and_agrees_with_statement(): void
    {
        Notification::fake();
        $stay = $this->stay();
        $reviewer = User::findOrFail($stay->reserved_by);
        DB::table('reservation_payment_policy')->where('id', 1)->update(['vrl_can_verify_pos' => true]);
        $stat = function () {
            $stats = (new \ReflectionMethod(\App\Filament\Widgets\StatsOverview::class, 'getStats'))->invoke(new \App\Filament\Widgets\StatsOverview);
            return collect($stats)->first(fn ($item) => $item->getLabel() === 'Verified Receipts (30d)')->getValue();
        };
        foreach (['FIRST', 'BALANCE'] as $reference) {
            $id = DB::table('assisted_reservation_payments')->insertGetId(['booking_id' => $stay->id, 'submitted_by' => $reviewer->id, 'amount_kobo' => 6046875, 'method' => $reference === 'FIRST' ? 'pos' : 'bank_transfer', 'reference' => $reference, 'transaction_date' => today()->toDateString(), 'proof_path' => 'private.png']);
            $this->assertSame($reference === 'FIRST' ? '₦0.00' : '₦60,468.75', $stat());
            app(AssistedReservationPayments::class)->verify($stay->fresh(), $id, $reviewer);
            $this->assertSame($reference === 'FIRST' ? '₦60,468.75' : '₦120,937.50', $stat());
        }
        $report = app(\App\Services\IplFinancialStatement::class)->report(today(config('property.timezone'))->toDateString(), today(config('property.timezone'))->toDateString());
        $this->assertSame(12093750, $report['inflow_cents']);
        $this->assertCount(2, $report['rows']);
        Notification::assertSentOnDemandTimes(CustomerPaymentEmail::class, 2);
    }

    public function test_branded_lifecycle_acknowledgement_is_distinct_from_verified_receipt(): void
    {
        Notification::fake();
        $stay = $this->stay();
        ReservationGuestCommunication::sendUpdate($stay, 'proof_submitted');
        Notification::assertSentOnDemand(CustomerPaymentEmail::class, function ($mail, $channels, $recipient) {
            $this->assertSame('guest@example.test', $recipient->routes['mail']);
            $this->assertFalse($mail->details['receipt']);
            $this->assertSame(0, $mail->details['total_paid_kobo']);
            $this->assertStringContainsString('not a verified payment receipt', $mail->details['update_text']);
            return $mail->details['reservation_update'];
        });
    }

    public function test_update_email_renders_logo_and_escapes_guest_details(): void
    {
        $stay = $this->stay();
        ReservationGuestCommunication::sendUpdate($stay, 'held');
        $job = DB::table('jobs')->sole();
        $command = unserialize(json_decode($job->payload, true)['data']['command']);
        $notification = $command->notification;
        Notification::sendNow((new \Illuminate\Notifications\AnonymousNotifiable())->route('mail', 'guest@example.test'), $notification);
        $html = Mail::getSymfonyTransport()->messages()->last()->getOriginalMessage()->getHtmlBody();
        $this->assertStringContainsString('cid:', $html);
        $this->assertStringContainsString('temporary room hold', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('NGN 0.00', $html);
    }

    public function test_guest_without_email_gets_no_mail_and_whatsapp_draft_has_correct_pending_and_paid_states(): void
    {
        Notification::fake();
        $stay = $this->stay(null);
        ReservationGuestCommunication::sendUpdate($stay, 'held');
        Notification::assertSentOnDemandTimes(CustomerPaymentEmail::class, 0);
        $url = ReservationGuestCommunication::whatsappUrl($stay);
        $this->assertStringStartsWith('https://wa.me/2349012345678?text=', $url);
        $this->assertStringContainsString('Verified payments: NGN 0.00', rawurldecode($url));
        $stay->update(['status' => 'confirmed', 'paid_at' => now(), 'hold_expires_at' => null]);
        $paid = rawurldecode(ReservationGuestCommunication::whatsappUrl($stay));
        $this->assertStringContainsString('Verified payments: NGN 120,937.50', $paid);
        $this->assertStringContainsString('Balance before check-in: NGN 0.00', $paid);
        $this->assertStringNotContainsString('Temporary hold expires', $paid);
        $stay->booking_contact = ['phone' => '12'];
        $this->assertNull(ReservationGuestCommunication::whatsappUrl($stay));
    }

    public function test_proof_upload_sends_acknowledgement_then_verification_sends_receipt_and_updates_payment_label(): void
    {
        $stay = $this->stay();
        Notification::fake();
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->actingAs(User::findOrFail($stay->reserved_by));
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('operations'));
        $proof = \Illuminate\Http\UploadedFile::fake()->createWithContent('pos.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a1uoAAAAASUVORK5CYII='));
        $page = \App\Filament\Operations\Resources\GuestStayResource\Pages\ViewGuestStay::class;
        \Livewire\Livewire::test($page, ['record' => $stay->id])->mountAction('recordPosPayment')
            ->set('mountedActionsData.0.proof', [$proof])->set('mountedActionsData.0.amount', '60468.75')
            ->set('mountedActionsData.0.method', 'pos')->set('mountedActionsData.0.reference', 'ACK-POS')
            ->set('mountedActionsData.0.transaction_date', today(config('property.timezone'))->toDateString())
            ->callMountedAction()->assertHasNoActionErrors();
        Notification::assertSentOnDemand(CustomerPaymentEmail::class, fn ($mail) => ($mail->details['reservation_update'] ?? false) && $mail->details['title'] === 'Payment proof received — verification pending');
        $this->assertSame(0, AssistedReservationPayments::verified($stay));
        app(AssistedReservationPayments::class)->verify($stay, AssistedReservationPayments::entries($stay)->sole()->id, User::factory()->create(['role' => User::ROLE_IPL_ADMIN]));
        Notification::assertSentOnDemandTimes(CustomerPaymentEmail::class, 2);
        \Livewire\Livewire::test($page, ['record' => $stay->id])->assertSee('Part payment verified · Balance outstanding')->assertSee('POS')->assertActionVisible('sendGuestWhatsApp');
    }

    public function test_assisted_arrival_and_departure_each_queue_a_guest_update(): void
    {
        $stay = $this->stay();
        Notification::fake();
        $this->travelTo(\Illuminate\Support\Carbon::parse($stay->check_in.' 14:00:00', config('property.timezone')));
        $stay->update(['status' => 'confirmed', 'paid_at' => now(), 'hold_expires_at' => null]);
        $service = app(\App\Services\StayOperationsService::class);
        $staff = User::findOrFail($stay->reserved_by);
        $service->checkIn($stay, $staff, ['full_name' => 'Guest', 'phone' => '09012345678', 'adults' => 1, 'children' => 0, 'identity_verified' => true]);
        $service->checkOut($stay->fresh(), $staff);
        Notification::assertSentOnDemandTimes(CustomerPaymentEmail::class, 2);
        Notification::assertSentOnDemand(CustomerPaymentEmail::class, fn ($mail) => $mail->details['title'] === 'Welcome — your check-in is complete' && $mail->details['balance_kobo'] === 0);
        Notification::assertSentOnDemand(CustomerPaymentEmail::class, fn ($mail) => $mail->details['title'] === 'Your check-out is complete' && $mail->details['balance_kobo'] === 0);
    }
}
