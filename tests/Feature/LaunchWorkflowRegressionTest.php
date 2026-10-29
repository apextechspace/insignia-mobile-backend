<?php

namespace Tests\Feature;

use App\Models\ConsumableItem;
use App\Models\FoodOrder;
use App\Models\ManualBankAccount;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\Room;
use App\Models\Service;
use App\Models\User;
use App\Services\FlutterwaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class LaunchWorkflowRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function product(int $stock = 5): Product
    {
        return Product::create(['name' => 'Live Product', 'category' => 'Decor', 'price' => 12000, 'stock' => $stock]);
    }

    private function order(Product $product, string $reference): Order
    {
        $order = Order::create([
            'user_id' => User::factory()->create()->id, 'product_id' => $product->id,
            'quantity' => 2, 'total_price' => 24000, 'status' => 'pending',
            'flutterwave_reference' => $reference, 'payment_method' => 'flutterwave',
        ]);
        PaymentAttempt::create([
            'gateway' => 'flutterwave', 'reference' => $reference,
            'payable_type' => Order::class, 'payable_id' => $order->id,
            'amount' => 24000, 'currency' => 'NGN', 'status' => 'initiated',
        ]);
        return $order;
    }

    private function webhook(string $reference, int $transactionId, int $times = 1, ?\Closure $afterFirst = null): void
    {
        $gateway = Mockery::mock(FlutterwaveService::class);
        $gateway->shouldReceive('verifyWebhookSignature')->times($times)->andReturn(true);
        $gateway->shouldReceive('verifyTransaction')->times($times)->with($transactionId)->andReturn([
            'success' => true, 'status' => 'SUCCESSFUL',
            'data' => ['id' => $transactionId, 'tx_ref' => $reference,
                'status' => 'successful', 'currency' => 'NGN', 'amount' => 24000],
        ]);
        $this->app->instance(FlutterwaveService::class, $gateway);
        for ($i = 0; $i < $times; $i++) {
            $this->postJson('/api/payments/flutterwave/webhook', [
                'event' => 'charge.completed',
                'data' => ['id' => $transactionId, 'tx_ref' => $reference, 'status' => 'successful'],
            ], ['verif-hash' => 'test-hash'])->assertOk();
            if ($i === 0 && $afterFirst) $afterFirst();
        }
    }

    public function test_guest_can_browse_catalogs_but_cannot_create_or_read_private_bookings(): void
    {
        $room = Room::create([
            'name' => 'Public Suite', 'type' => 'Suite', 'price_per_night' => 65000,
            'status' => 'available', 'is_available' => true, 'housekeeping_notes' => 'Private staff notes',
        ]);
        foreach (['rooms', 'services', 'products', 'menu', 'menu/categories'] as $path) {
            $this->getJson('/api/' . $path)->assertOk()->assertDontSee('Private staff notes');
        }
        $this->getJson('/api/rooms/' . $room->id)->assertOk()->assertJsonMissingPath('housekeeping_notes');
        $room->update(['status' => 'maintenance']);
        $this->getJson('/api/rooms/' . $room->id)->assertNotFound();
        $this->getJson('/api/bookings')->assertUnauthorized();
        $this->postJson('/api/orders', [])->assertUnauthorized();
    }

    public function test_pending_boutique_order_does_not_consume_stock_and_optional_fields_can_be_omitted(): void
    {
        $product = $this->product();
        $this->actingAs(User::factory()->create())->postJson('/api/orders', [
            'product_id' => $product->id, 'quantity' => 2,
        ])->assertCreated()->assertJsonPath('status', 'pending')->assertJsonPath('notes', null);
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_verified_boutique_payment_deducts_stock_exactly_once(): void
    {
        $product = $this->product();
        $order = $this->order($product, 'STOCK-ONCE');
        $this->webhook('STOCK-ONCE', 801, 2);
        $this->assertSame(3, $product->fresh()->stock);
        $this->assertNotNull($order->fresh()->paid_at);
        $this->assertSame('confirmed', $order->fresh()->status);
    }

    public function test_legacy_reserved_order_is_not_charged_inventory_twice(): void
    {
        $product = $this->product(0);
        $order = $this->order($product, 'LEGACY-RESERVATION');
        $order->forceFill(['stock_deducted' => true])->save();
        $this->webhook('LEGACY-RESERVATION', 804);
        $this->assertNotNull($order->fresh()->paid_at);
        $this->assertSame(0, $product->fresh()->stock);
    }

    public function test_out_of_stock_order_cannot_start_a_new_checkout(): void
    {
        $order = $this->order($this->product(1), 'NO-STOCK');
        $this->actingAs($order->user)->postJson('/api/payments/flutterwave/initialize', [
            'order_type' => 'order', 'order_id' => $order->id,
        ])->assertConflict();
        $this->assertDatabaseCount('payment_attempts', 1);
    }

    public function test_stock_shortage_after_payment_requires_finance_review_and_does_not_auto_fulfill_on_replay(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_IPL_ADMIN]);
        $product = $this->product(1);
        $order = $this->order($product, 'STOCK-SHORT');
        $this->webhook('STOCK-SHORT', 802, 2, function () use ($order, $product): void {
            $this->assertNull($order->fresh()->paid_at);
            $this->assertSame(1, $product->fresh()->stock);
            $this->assertDatabaseHas('payment_reconciliation_cases', ['payment_reference' => 'STOCK-SHORT', 'reason' => 'product_out_of_stock']);
            $product->update(['stock' => 10]);
        });
        $this->assertNull($order->fresh()->paid_at);
        $this->assertSame(10, $product->fresh()->stock);
        $this->assertSame(1, $owner->notifications()->where('data->type', 'payment_review')->count());
    }

    public function test_provider_id_reuse_is_recorded_without_overwriting_original_evidence_or_failing_the_webhook(): void
    {
        $product = $this->product();
        $first = $this->order($product, 'ORIGINAL-TXN');
        PaymentAttempt::where('reference', 'ORIGINAL-TXN')->update(['provider_transaction_id' => '803', 'status' => 'confirmed']);
        $second = $this->order($product, 'REUSED-TXN');
        $this->webhook('REUSED-TXN', 803);
        $this->assertNull($second->fresh()->paid_at);
        $this->assertDatabaseHas('payment_attempts', ['reference' => 'ORIGINAL-TXN', 'provider_transaction_id' => '803']);
        $this->assertDatabaseHas('payment_attempts', ['reference' => 'REUSED-TXN', 'provider_transaction_id' => null, 'status' => 'needs_review']);
        $this->assertDatabaseHas('payment_reconciliation_cases', ['payment_reference' => 'REUSED-TXN', 'provider_transaction_id' => '803', 'reason' => 'transaction_id_reused']);
    }

    public function test_wrong_profile_pin_is_a_validation_error_and_does_not_log_out_the_user(): void
    {
        $user = User::factory()->create(['security_pin' => Hash::make('1234')]);
        $this->actingAs($user)->postJson('/api/user/verify-pin', ['pin' => '9999'])->assertUnprocessable();
        $this->getJson('/api/user')->assertOk()->assertJsonPath('id', $user->id);
    }

    public function test_push_token_moves_to_current_account_and_invalid_tokens_are_rejected(): void
    {
        $previous = User::factory()->create(['expo_push_token' => 'ExpoPushToken[shared]']);
        $current = User::factory()->create();
        $this->actingAs($current)->postJson('/api/user/push-token', ['token' => 'ExpoPushToken[shared]'])->assertOk();
        $this->assertNull($previous->fresh()->expo_push_token);
        $this->assertSame('ExpoPushToken[shared]', $current->fresh()->expo_push_token);
        $this->postJson('/api/user/push-token', ['token' => 'invalid'])->assertUnprocessable();
    }

    public function test_logout_clears_device_push_and_revokes_access_token(): void
    {
        $user = User::factory()->create(['expo_push_token' => 'ExpoPushToken[logout]']);
        $token = $user->createToken('logout-test')->plainTextToken;
        $this->withToken($token)->postJson('/api/logout')->assertOk();
        $this->assertNull($user->fresh()->expo_push_token);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_inventory_requires_a_staff_role_and_returns_actual_stock(): void
    {
        $item = ConsumableItem::create([
            'name' => 'Bath towels', 'category' => 'linen', 'unit' => 'pieces',
            'current_stock' => 2, 'minimum_alert_level' => 3, 'unit_cost' => 1500,
        ]);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_USER]))->getJson('/api/inventory')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_STAFF]))->getJson('/api/inventory')
            ->assertOk()->assertJsonPath('0.id', $item->id)->assertJsonPath('0.current_stock', 2)
            ->assertJsonPath('0.is_low_stock', true)->assertJsonMissingPath('0.unit_cost');
    }

    public function test_dining_history_is_scoped_to_the_customer(): void
    {
        $mine = User::factory()->create();
        $other = User::factory()->create();
        foreach ([$mine, $other] as $user) {
            FoodOrder::create(['user_id' => $user->id, 'type' => 'pickup', 'total_amount' => 5000, 'status' => 'pending']);
        }
        $this->actingAs($mine)->getJson('/api/food-orders')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.user_id', $mine->id);
    }

    public function test_unavailable_dining_item_returns_validation_error_without_creating_an_order(): void
    {
        $category = MenuCategory::create(['name' => 'Lunch', 'is_active' => true]);
        $item = MenuItem::create(['menu_category_id' => $category->id, 'name' => 'Lunch plate', 'price' => 5000, 'is_available' => false]);
        $this->actingAs(User::factory()->create())->postJson('/api/food-orders', [
            'type' => 'pickup', 'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ])->assertUnprocessable();
        $this->assertDatabaseCount('food_orders', 0);
    }

    public function test_inactive_service_cannot_be_booked(): void
    {
        $service = Service::create(['name' => 'Closed service', 'category' => 'Wellness', 'type' => 'booking', 'price' => 5000, 'is_active' => false]);
        $this->actingAs(User::factory()->create())->postJson('/api/service-bookings', ['service_id' => $service->id])->assertUnprocessable();
        $this->assertDatabaseCount('service_bookings', 0);
    }

    public function test_direct_transfer_is_not_offered_for_unimplemented_commerce_approval_flows(): void
    {
        ManualBankAccount::create(['bank_name' => 'Example', 'account_name' => 'Test Ltd', 'account_number' => '0123456789', 'is_active' => true]);
        $this->actingAs(User::factory()->create());
        foreach (['order', 'food_order', 'service_booking'] as $type) {
            $this->getJson('/api/payment-methods/manual-bank?order_type=' . $type)
                ->assertOk()->assertJsonPath('is_configured', false)->assertJsonPath('account_number', null);
        }
        $product = $this->product();
        $this->post('/api/orders', ['product_id' => $product->id, 'quantity' => 1, 'receipt' => UploadedFile::fake()->image('proof.jpg')], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_payment_initialization_rejects_arbitrary_redirects_and_accepts_configured_web_return(): void
    {
        $order = $this->order($this->product(), 'WEB-CALLBACK');
        $this->actingAs($order->user);
        config(['services.flutterwave.web_return_url' => 'https://app.example.test/payment-complete']);
        $gateway = Mockery::mock(FlutterwaveService::class);
        $gateway->shouldReceive('createPaymentLink')->once()->withArgs(fn ($data) =>
            str_starts_with($data['redirect_url'], 'https://app.example.test/payment-complete?')
            && str_contains($data['redirect_url'], 'order_type=order')
        )->andReturn(['success' => true, 'payment_url' => 'https://checkout.example.test/pay']);
        $this->app->instance(FlutterwaveService::class, $gateway);
        $this->postJson('/api/payments/flutterwave/initialize', [
            'order_type' => 'order', 'order_id' => $order->id, 'redirect_url' => 'https://untrusted.example/payment-complete',
        ])->assertUnprocessable();
        $this->postJson('/api/payments/flutterwave/initialize', [
            'order_type' => 'order', 'order_id' => $order->id, 'redirect_url' => 'https://app.example.test/payment-complete',
        ])->assertOk();
    }
}
