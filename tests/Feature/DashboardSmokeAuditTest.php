<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardSmokeAuditTest extends TestCase
{
    use RefreshDatabase;

    private function auditPanel(string $panelId, string $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]));
        $panel = Filament::getPanel($panelId);
        Filament::setCurrentPanel($panel);
        $this->get('/'.$panelId)->assertOk();
        foreach ($panel->getResources() as $resource) {
            if (! array_key_exists('index', $resource::getPages())) {
                continue;
            }
            $url = $resource::getUrl('index', panel: $panelId);
            $response = $this->get($url);
            if ($resource::canViewAny()) {
                $response->assertOk();
            } else {
                $response->assertForbidden();
            }
        }
    }

    public function test_owner_dashboard_and_all_owner_resource_indexes_render(): void
    {
        $this->auditPanel('admin', User::ROLE_IPL_ADMIN);
        foreach ([\App\Filament\Widgets\OwnerCommandWidget::class, \App\Filament\Widgets\StatsOverview::class, \App\Filament\Widgets\OrdersChart::class] as $widget) {
            Livewire::withoutLazyLoading()->test($widget)->assertSuccessful();
        }
        foreach ([\App\Filament\Pages\IplMasterStatement::class, \App\Filament\Pages\AccommodationReconciliationReport::class, \App\Filament\Pages\ManualBankSettings::class] as $page) {
            $this->get($page::getUrl(panel: 'admin'))->assertOk();
        }
    }

    public function test_mcl_dashboard_and_resource_visibility_render(): void
    {
        $this->auditPanel('operations', User::ROLE_DELIVERY_PARTNER);
        Livewire::withoutLazyLoading()->test(\App\Filament\Operations\Widgets\OperationsDashboardWidget::class)->assertSuccessful();
    }

    public function test_staff_dashboard_and_resource_visibility_render(): void
    {
        $this->auditPanel('operations', User::ROLE_STAFF);
        Livewire::withoutLazyLoading()->test(\App\Filament\Operations\Widgets\OperationsDashboardWidget::class)
            ->assertSee('My Tasks')->assertSee('My Shift')->assertDontSee('Pending Room Bookings');
        $this->get(\App\Filament\Operations\Resources\AssetResource::getUrl('create', panel: 'operations'))->assertForbidden();
        $this->get(\App\Filament\Operations\Resources\IncidentResource::getUrl('create', panel: 'operations'))->assertForbidden();
        Livewire::test(\App\Filament\Operations\Resources\StaffTaskResource\Pages\ListStaffTasks::class)
            ->callAction('reportIssue', data: ['title' => 'Leaking tap', 'type' => 'maintenance_failure', 'description' => 'Reception sink'])
            ->assertHasNoActionErrors();
        $this->assertDatabaseHas('incidents', ['title' => 'Leaking tap', 'reported_by' => auth()->id(), 'status' => 'reported']);
        $this->get('/admin')->assertForbidden();
    }

    public function test_front_desk_dashboard_and_resource_visibility_render(): void
    {
        $this->auditPanel('operations', User::ROLE_GUEST_RELATIONS);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-12 23:30:00', 'UTC'));
        $guest = User::factory()->create(['name' => 'Lagos Midnight Arrival']);
        $room = \App\Models\Room::create([
            'name' => 'Front Desk Test Suite', 'type' => 'Suite', 'price_per_night' => 1000, 'status' => 'cleaning',
        ]);
        \App\Models\Booking::create([
            'user_id' => $guest->id, 'room_id' => $room->id, 'check_in' => '2026-11-13',
            'check_out' => '2026-11-14', 'status' => 'confirmed', 'paid_at' => now(), 'total_price' => 1000,
        ]);
        Livewire::withoutLazyLoading()->test(\App\Filament\Operations\Widgets\OperationsDashboardWidget::class)
            ->assertSee('Today’s arrivals')
            ->assertSee('Lagos Midnight Arrival')
            ->assertSee('Room still being cleaned')
            ->assertSee('Departures due')
            ->assertSee('Needs Attention')
            ->assertDontSee('Pending Room Bookings');
        $this->get('/admin')->assertForbidden();
    }
}
