<?php

namespace Tests\Feature;

use App\Filament\Auxiliary\Resources\FoodOrderResource;
use App\Filament\Auxiliary\Resources\FoodOrderResource\Pages\ListFoodOrders;
use App\Models\FoodOrder;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuxiliaryPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_operations_expense_query_excludes_auxiliary_and_unclassified_records(): void
    {
        $manager = User::factory()->create(['role' => User::ROLE_DELIVERY_PARTNER]);
        $this->actingAs($manager);
        foreach (['accommodation', 'auxiliary', null] as $stream) {
            \App\Models\Expense::create([
                'title' => 'Expense', 'financial_stream' => $stream, 'category' => 'other',
                'amount' => 100, 'logged_by' => $manager->id, 'expense_date' => now(),
            ]);
        }
        $query = \App\Filament\Operations\Resources\ExpenseResource::getEloquentQuery();
        $this->assertSame(['accommodation'], $query->pluck('financial_stream')->all());
        $this->actingAs(User::factory()->create(['role' => User::ROLE_IPL_ADMIN]));
        $this->assertSame(3, \App\Filament\Operations\Resources\ExpenseResource::getEloquentQuery()->count());
    }

    public function test_auxiliary_can_fulfil_only_verified_orders_and_cannot_access_other_panels(): void
    {
        $aux = User::factory()->create(['role' => User::ROLE_AUXILIARY]);
        $guest = User::factory()->create();
        $this->actingAs($aux);
        Filament::setCurrentPanel(Filament::getPanel('auxiliary'));
        $paid = FoodOrder::create(['user_id' => $guest->id, 'total_amount' => 1500, 'status' => 'paid', 'paid_at' => now(), 'type' => 'pickup']);
        $unpaid = FoodOrder::create(['user_id' => $guest->id, 'total_amount' => 1500, 'status' => 'pending', 'type' => 'pickup']);
        $this->get('/auxiliary')->assertOk()->assertSee('Kitchen')->assertSee('Services');
        foreach (Filament::getPanel('auxiliary')->getResources() as $resource) {
            $this->get($resource::getUrl(panel: 'auxiliary'))->assertOk();
        }
        Livewire::test(ListFoodOrders::class)
            ->assertCanSeeTableRecords([$paid])->assertCanNotSeeTableRecords([$unpaid])
            ->mountTableAction('details', $paid)->assertSee('Order #'.$paid->id)
            ->unmountTableAction()
            ->callTableAction('progress_paid', $paid)->assertHasNoTableActionErrors();
        $this->assertSame('preparing', $paid->fresh()->status);
        Livewire::test(ListFoodOrders::class)->callTableAction('progress_preparing', $paid->fresh());
        $this->assertSame('delivered', $paid->fresh()->status);
        $this->assertDatabaseHas('auxiliary_fulfilment_events', ['order_id' => $paid->id, 'handled_by' => $aux->id, 'status' => 'delivered']);
        $this->assertEquals(1500, $paid->fresh()->total_amount);
        $this->get('/admin')->assertForbidden();
        $this->get('/operations')->assertForbidden();
    }

    public function test_shop_and_service_transitions_preserve_the_payment(): void
    {
        $aux = User::factory()->create(['role' => User::ROLE_AUXILIARY]);
        $guest = User::factory()->create();
        $this->actingAs($aux);
        Filament::setCurrentPanel(Filament::getPanel('auxiliary'));
        $product = \App\Models\Product::create(['name' => 'Tea', 'category' => 'accessories', 'description' => 'Tea', 'price' => 1000, 'stock' => 10]);
        $order = \App\Models\Order::create(['user_id' => $guest->id, 'product_id' => $product->id,
            'quantity' => 1, 'total_price' => 1000, 'status' => 'confirmed', 'paid_at' => now()]);
        Livewire::test(\App\Filament\Auxiliary\Resources\OrderResource\Pages\ListOrders::class)
            ->callTableAction('progress_confirmed', $order)->assertHasNoTableActionErrors();
        $this->assertSame('processing', $order->fresh()->status);
        $service = \App\Models\Service::create(['name' => 'Coworking', 'category' => 'workspace', 'type' => 'booking', 'price' => 5000]);
        $booking = \App\Models\ServiceBooking::create(['user_id' => $guest->id, 'service_id' => $service->id,
            'total_price' => 5000, 'status' => 'confirmed', 'paid_at' => now()]);
        Livewire::test(\App\Filament\Auxiliary\Resources\ServiceBookingResource\Pages\ListServiceBookings::class)
            ->callTableAction('progress_confirmed', $booking)->assertHasNoTableActionErrors();
        $this->assertSame('in-progress', $booking->fresh()->status);
        $this->assertNotNull($booking->fresh()->paid_at);
    }

    public function test_operations_roles_cannot_open_auxiliary_orders_or_old_commerce_notifications(): void
    {
        foreach ([User::ROLE_DELIVERY_PARTNER, User::ROLE_GUEST_RELATIONS, User::ROLE_STAFF] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user);
            $this->get('/auxiliary')->assertForbidden();
            $this->get(FoodOrderResource::getUrl(panel: 'auxiliary'))->assertForbidden();
            $user->notify(new \App\Notifications\VerifiedCommercePayment('food_order', 1, 'Dining', 'Paid'));
            $this->assertSame(0, $user->notifications()->count());
        }
    }
}
