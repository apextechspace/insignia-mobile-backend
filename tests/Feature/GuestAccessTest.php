<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\GuestAccessEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class GuestAccessTest extends TestCase
{
    use RefreshDatabase;

    private function access(string $email = 'guest@example.test', array $details = []): array
    {
        Notification::fake();
        $id = $this->postJson('/api/guest-access/request', ['email' => $email] + $details)->assertOk()->json('request_id');
        $sent = null;
        Notification::assertSentOnDemand(GuestAccessEmail::class, function ($notification) use (&$sent) {
            $sent = $notification;

            return true;
        });

        return [$id, $sent];
    }

    public function test_no_customer_or_session_exists_until_email_is_verified(): void
    {
        [$id, $mail] = $this->access('guest@example.test', ['name' => 'Ada Guest', 'phone' => '+2348001112222']);
        $this->assertDatabaseCount('users', 0);
        $stored = DB::table('guest_access_requests')->find($id);
        $this->assertNotSame($mail->code, $stored->code_hash);
        $response = $this->postJson('/api/guest-access/verify', ['request_id' => $id, 'code' => $mail->code])->assertOk();
        $this->assertNotEmpty($response->json('access_token'));
        $this->assertSame('Ada Guest', $response->json('user.name'));
        $this->assertNotNull(User::first()->email_verified_at);
        $this->assertSame('user', User::first()->role);
        $this->postJson('/api/guest-access/verify', ['request_id' => $id, 'code' => $mail->code])->assertUnprocessable();
    }

    public function test_magic_link_requires_post_and_is_single_use(): void
    {
        [$id, $mail] = $this->access();
        parse_str(parse_url($mail->url, PHP_URL_FRAGMENT), $fragment);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->postJson('/api/guest-access/verify', $fragment)->assertOk();
        $this->postJson('/api/guest-access/verify', $fragment)->assertUnprocessable();
    }

    public function test_wrong_codes_are_limited_and_expired_codes_cannot_sign_in(): void
    {
        [$id, $mail] = $this->access();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/guest-access/verify', ['request_id' => $id, 'code' => '000000'])->assertUnprocessable();
        }
        $this->postJson('/api/guest-access/verify', ['request_id' => $id, 'code' => $mail->code])->assertUnprocessable();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_expired_access_link_is_rejected(): void
    {
        [$id, $mail] = $this->access();
        $this->travel(16)->minutes();
        $this->postJson('/api/guest-access/verify', ['request_id' => $id, 'code' => $mail->code])->assertUnprocessable();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_existing_customer_is_reused_without_identity_overwrite(): void
    {
        $user = User::factory()->create(['email' => 'guest@example.test', 'name' => 'Original', 'phone' => '08011111111']);
        [$id, $mail] = $this->access('GUEST@example.test', ['name' => 'Replacement', 'phone' => '08022222222']);
        $this->postJson('/api/guest-access/verify', ['request_id' => $id, 'code' => $mail->code])->assertOk()->assertJsonPath('user.id', $user->id);
        $this->assertSame('Original', $user->fresh()->name);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_staff_addresses_do_not_receive_customer_access(): void
    {
        User::factory()->create(['email' => 'owner@example.test', 'role' => 'ipl_admin']);
        Notification::fake();
        $id = $this->postJson('/api/guest-access/request', ['email' => 'owner@example.test'])->assertOk()->json('request_id');
        Notification::assertNothingSent();
        $row = DB::table('guest_access_requests')->find($id);
        DB::table('guest_access_requests')->where('id', $id)->update(['code_hash' => hash_hmac('sha256', '123456', config('app.key'))]);
        $this->postJson('/api/guest-access/verify', ['request_id' => $id, 'code' => '123456'])->assertUnprocessable();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_verified_guest_token_only_lists_its_own_records(): void
    {
        User::factory()->create();
        [$id, $mail] = $this->access();
        $token = $this->postJson('/api/guest-access/verify', ['request_id' => $id, 'code' => $mail->code])->assertOk()->json('access_token');
        $this->withToken($token)->getJson('/api/bookings')->assertOk()->assertExactJson([]);
        $this->withToken($token)->getJson('/api/orders')->assertOk();
        $this->withToken($token)->getJson('/api/service-bookings')->assertOk();
    }

    public function test_guest_email_session_can_book_pay_and_return_to_the_same_dashboard(): void
    {
        [$id, $mail] = $this->access('booking@example.test', ['name' => 'Booking Guest', 'phone' => '08011112222']);
        $token = $this->postJson('/api/guest-access/verify', ['request_id' => $id, 'code' => $mail->code])->assertOk()->json('access_token');
        $room = \App\Models\Room::create(['name' => 'Guest Suite', 'type' => 'Suite', 'price_per_night' => 50000, 'is_available' => true, 'status' => 'available']);
        $bookingId = $this->withToken($token)->postJson('/api/bookings', ['room_id' => $room->id,
            'check_in' => today()->addDays(3)->toDateString(), 'check_out' => today()->addDays(5)->toDateString(),
            'guests' => 2, 'guest_name' => 'Booking Guest', 'guest_phone' => '08011112222',
        ])->assertCreated()->json('id');
        $gateway = \Mockery::mock(\App\Services\FlutterwaveService::class);
        $gateway->shouldReceive('createPaymentLink')->once()->andReturn(['success' => true, 'payment_url' => 'https://checkout.example.test/guest']);
        $this->app->instance(\App\Services\FlutterwaveService::class, $gateway);
        $reference = $this->withToken($token)->postJson('/api/payments/flutterwave/initialize', ['order_type' => 'booking', 'order_id' => $bookingId])->assertOk()->json('reference');
        $gateway->shouldReceive('verifyWebhookSignature')->andReturn(true);
        $gateway->shouldReceive('verifyTransaction')->twice()->andReturn(['success' => true, 'status' => 'SUCCESSFUL', 'data' => [
            'id' => 12345, 'tx_ref' => $reference, 'status' => 'successful', 'currency' => 'NGN', 'amount' => 100000,
        ]]);
        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/payments/flutterwave/webhook', ['event' => 'charge.completed',
                'data' => ['id' => 12345, 'tx_ref' => $reference, 'status' => 'successful']], ['verif-hash' => 'testing'])->assertOk();
        }
        $this->withToken($token)->getJson('/api/bookings')->assertOk()->assertJsonCount(1)->assertJsonPath('0.status', 'confirmed');
        $this->assertDatabaseCount('accommodation_ledgers', 1);
        Notification::assertSentTo(User::where('email', 'booking@example.test')->first(), \App\Notifications\CustomerPaymentEmail::class);
    }

    public function test_fresh_verified_customer_can_set_a_password_but_old_session_cannot(): void
    {
        [$id, $mail] = $this->access();
        $token = $this->postJson('/api/guest-access/verify', ['request_id' => $id, 'code' => $mail->code])->assertOk()->json('access_token');
        $password = 'TestGuest!123456';
        $this->withToken($token)->postJson('/api/user/password', ['password' => $password, 'password_confirmation' => $password])->assertOk();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check($password, User::first()->password));
        $this->travel(16)->minutes();
        $this->withToken($token)->postJson('/api/user/password', ['password' => $password, 'password_confirmation' => $password])->assertForbidden();
    }
}
