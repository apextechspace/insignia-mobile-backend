<?php

namespace Tests\Feature;

use App\Filament\Operations\Resources\IncidentResource\Pages\CreateIncident;
use App\Filament\Operations\Resources\IncidentResource\Pages\EditIncident;
use App\Models\Incident;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IncidentCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('operations'));
        $this->actingAs(User::factory()->create(['role' => User::ROLE_GUEST_RELATIONS]));
    }

    public function test_front_desk_can_create_an_incident_with_a_blank_cost(): void
    {
        Livewire::test(CreateIncident::class)
            ->fillForm([
                'title' => 'Guest reported a missing item',
                'type' => 'missing_item',
                'description' => 'Front desk is investigating the report.',
                'associated_cost' => null,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $incident = Incident::sole();
        $this->assertSame('0.00', $incident->associated_cost);
        $this->assertSame('reported', $incident->status);
        $this->assertEquals(auth()->id(), $incident->reported_by);
        $this->assertStringStartsWith('INC-', $incident->incident_code);
    }

    public function test_entered_cost_is_preserved(): void
    {
        Livewire::test(CreateIncident::class)
            ->fillForm([
                'title' => 'Damaged fixture', 'type' => 'property_damage',
                'description' => 'Fixture needs replacement.', 'associated_cost' => '12500.50',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('12500.50', Incident::sole()->associated_cost);
    }

    public function test_front_desk_can_clear_cost_when_editing_an_incident(): void
    {
        $incident = Incident::create([
            'title' => 'Guest complaint', 'type' => 'guest_complaint',
            'description' => 'Front desk report.', 'reported_by' => auth()->id(),
            'associated_cost' => 1000,
        ]);

        Livewire::test(EditIncident::class, ['record' => $incident->getRouteKey()])
            ->fillForm(['associated_cost' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('0.00', $incident->fresh()->associated_cost);
    }
}
