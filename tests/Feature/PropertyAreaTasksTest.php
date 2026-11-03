<?php

namespace Tests\Feature;

use App\Filament\Operations\Resources\PropertyAreaResource;
use App\Filament\Operations\Resources\StaffTaskResource;
use App\Filament\Operations\Resources\StaffTaskResource\Pages\CreateStaffTask;
use App\Filament\Operations\Resources\StaffTaskResource\Pages\EditStaffTask;
use App\Filament\Operations\Resources\StaffTaskResource\Pages\ListStaffTasks;
use App\Models\PropertyArea;
use App\Models\Room;
use App\Models\StaffTask;
use App\Models\User;
use App\Services\PropertyAreaTaskService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PropertyAreaTasksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('operations'));
    }

    private function task(array $overrides = []): StaffTask
    {
        return StaffTask::create($overrides + [
            'property_area_id' => PropertyArea::where('name', 'Reception')->firstOrFail()->id,
            'task_title' => 'Clean reception', 'task_type' => 'daily_refresh', 'priority' => 'medium', 'status' => 'pending',
            'checklist_items' => StaffTask::areaCleaningChecklist(),
        ]);
    }

    public function test_initial_areas_are_editable_locations_and_not_bookable_rooms(): void
    {
        $this->assertDatabaseCount('property_areas', 6);
        $this->assertDatabaseCount('rooms', 0);
        $this->assertDatabaseHas('property_areas', ['name' => 'Upstairs Foyer']);
        $this->getJson('/api/rooms')->assertOk()->assertExactJson([]);
        $area = PropertyArea::first();
        $area->update(['name' => 'Renamed foyer']);
        $this->assertSame('Renamed foyer', $area->fresh()->name);
    }

    public function test_mcl_manager_can_create_and_assign_a_property_area_duty(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_DELIVERY_PARTNER]));
        $worker = User::factory()->create(['role' => User::ROLE_STAFF]);
        $area = PropertyArea::first();
        $this->assertTrue(PropertyAreaResource::canCreate());
        Livewire::test(CreateStaffTask::class)->fillForm([
            'task_title' => 'Sweep upstairs foyer', 'task_type' => 'daily_refresh', 'priority' => 'high',
            'location_type' => 'property_area', 'property_area_id' => $area->id,
            'user_id' => $worker->id, 'status' => 'pending', 'checklist_items' => StaffTask::areaCleaningChecklist(),
        ])->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseHas('staff_tasks', ['property_area_id' => $area->id, 'room_id' => null, 'user_id' => $worker->id, 'task_title' => 'Sweep upstairs foyer']);
    }

    public function test_staff_see_only_their_own_area_duties_and_cannot_manage_areas(): void
    {
        $worker = User::factory()->create(['role' => User::ROLE_STAFF]);
        $own = $this->task(['user_id' => $worker->id]);
        $other = $this->task(['user_id' => User::factory()->create(['role' => User::ROLE_STAFF])->id]);
        $this->actingAs($worker);
        $this->assertFalse(PropertyAreaResource::canViewAny());
        $this->assertFalse(StaffTaskResource::canCreate());
        Livewire::test(ListStaffTasks::class)->assertCanSeeTableRecords([$own])->assertCanNotSeeTableRecords([$other]);
        $this->get('/operations/property-areas')->assertForbidden();
    }

    public function test_checklist_completion_and_supervisor_verification_do_not_change_room_readiness(): void
    {
        $room = Room::create(['name' => 'Untouched room', 'type' => 'Suite', 'price_per_night' => 1000, 'status' => 'cleaning']);
        $worker = User::factory()->create(['role' => User::ROLE_STAFF]);
        $task = $this->task(['user_id' => $worker->id]);
        $this->actingAs($worker);
        Livewire::test(ListStaffTasks::class)->callTableAction('mark_complete', $task);
        $this->assertSame('pending', $task->fresh()->status);
        $done = array_map(fn ($item) => ['item' => $item['item'], 'done' => true], $task->checklist_items);
        Livewire::test(EditStaffTask::class, ['record' => $task->id])->fillForm(fn (array $state) => [
            'checklist_items' => array_map(fn (array $item) => ['item' => $item['item'], 'done' => true], $state['checklist_items']),
        ])->call('save')->assertHasNoFormErrors();
        $this->assertSame($done, $task->fresh()->checklist_items);
        Livewire::test(ListStaffTasks::class)->callTableAction('mark_complete', $task->fresh());
        $this->assertSame('completed', $task->fresh()->status);
        $supervisor = User::factory()->create(['role' => User::ROLE_DELIVERY_PARTNER]);
        app(PropertyAreaTaskService::class)->verify($task, $supervisor);
        $this->assertSame('verified', $task->fresh()->status);
        $this->assertSame($supervisor->id, $task->fresh()->verified_by);
        $this->assertNotNull($task->fresh()->verified_at);
        $this->assertSame('cleaning', $room->fresh()->status);
    }

    public function test_task_cannot_target_both_a_room_and_a_property_area(): void
    {
        $room = Room::create(['name' => 'Room', 'type' => 'Suite', 'price_per_night' => 1000]);
        $this->expectException(ValidationException::class);
        $this->task(['room_id' => $room->id]);
    }

    public function test_room_turnaround_cannot_be_assigned_to_a_property_area(): void
    {
        $this->expectException(ValidationException::class);
        $this->task(['task_type' => 'room_turnaround']);
    }

    public function test_retired_area_keeps_history_but_cannot_receive_new_tasks(): void
    {
        $task = $this->task();
        $area = $task->propertyArea;
        $area->update(['is_active' => false]);
        $task->update(['notes' => 'Existing duty retained']);
        $this->assertSame('Reception', $task->fresh()->location_name);
        $this->expectException(ValidationException::class);
        $this->task(['property_area_id' => $area->id]);
    }

    public function test_area_with_task_history_cannot_be_deleted(): void
    {
        $task = $this->task();
        $this->expectException(ValidationException::class);
        $task->propertyArea->delete();
    }

    public function test_staff_cannot_verify_their_own_area_task(): void
    {
        $worker = User::factory()->create(['role' => User::ROLE_STAFF]);
        $task = $this->task(['user_id' => $worker->id]);
        $this->expectException(ValidationException::class);
        app(PropertyAreaTaskService::class)->verify($task, $worker);
    }

    public function test_staff_cannot_remove_assigned_checklist_items_to_bypass_completion(): void
    {
        $worker = User::factory()->create(['role' => User::ROLE_STAFF]);
        $task = $this->task(['user_id' => $worker->id]);
        $this->actingAs($worker);
        Livewire::test(EditStaffTask::class, ['record' => $task->id])
            ->set('data.checklist_items', [['item' => 'Everything done', 'done' => true]])
            ->call('save')->assertHasFormErrors(['checklist_items']);
        $this->assertSame(StaffTask::areaCleaningChecklist(), $task->fresh()->checklist_items);
    }
}
