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
        Livewire::withoutLazyLoading()->test(\App\Filament\Operations\Widgets\OperationsDashboardWidget::class)->assertSuccessful();
        $this->get('/admin')->assertForbidden();
    }

    public function test_front_desk_dashboard_and_resource_visibility_render(): void
    {
        $this->auditPanel('operations', User::ROLE_GUEST_RELATIONS);
        $this->get('/admin')->assertForbidden();
    }
}
