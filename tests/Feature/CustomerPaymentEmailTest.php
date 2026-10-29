<?php

namespace Tests\Feature;

use App\Models\FoodOrder;
use App\Models\User;
use App\Notifications\CustomerPaymentEmail;
use App\Services\CustomerPaymentEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CustomerPaymentEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_unpaid_order_cannot_generate_receipt(): void
    {
        Notification::fake();
        $order = FoodOrder::create([
            'user_id' => User::factory()->create()->id,
            'total_amount' => 7500, 'status' => 'pending', 'type' => 'pickup',
        ]);
        app(CustomerPaymentEmailService::class)->send($order);
        Notification::assertNothingSent();
    }

    public function test_receipt_renders_with_embedded_logo_and_escaped_customer_details(): void
    {
        $user = User::factory()->create(['name' => '<script>Customer</script>']);
        $notification = new CustomerPaymentEmail([
            'title' => 'Payment receipt', 'receipt' => true,
            'number' => 'FoodOrder-42', 'description' => 'Dining order',
            'amount' => '7,500.00', 'reference' => 'VERIFIED-42',
            'method' => 'Flutterwave', 'date' => '2026-10-29 12:00:00',
            'check_in' => null, 'check_out' => null,
        ]);
        Notification::sendNow($user, $notification);
        $sent = Mail::getSymfonyTransport()->messages()->first()->getOriginalMessage();
        $html = $sent->getHtmlBody();
        $this->assertStringContainsString('cid:', $html);
        $this->assertStringContainsString('Insignia Courts', $html);
        $this->assertStringContainsString('NGN 7,500.00', $html);
        $this->assertStringContainsString('VERIFIED-42', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertCount(1, $sent->getAttachments());
    }
}
