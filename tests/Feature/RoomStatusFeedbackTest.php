<?php

namespace Tests\Feature;

use App\Filament\Operations\Resources\RoomStatusResource\Pages\EditRoomStatus;
use App\Filament\Operations\Resources\RoomReadinessResource\Pages\ListRoomReadiness;
use App\Filament\Operations\Resources\StaffTaskResource\Pages\EditStaffTask;
use App\Models\{Booking, Room, StaffTask, User};
use App\Services\RoomTurnoverService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class RoomStatusFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        Filament::setCurrentPanel(Filament::getPanel('operations'));
        $user = User::factory()->create(['role' => User::ROLE_DELIVERY_PARTNER]);
        $this->actingAs($user);
        return $user;
    }

    private function room(): Room
    {
        return Room::create(['name' => 'Papilio', 'type' => 'Suite', 'price_per_night' => 161250,
            'images' => ['rooms/papilio.jpg'], 'status' => 'cleaning', 'is_available' => true]);
    }

    public function test_blocked_ready_edit_shows_a_visible_notification_and_inline_field_error(): void
    {
        $this->manager(); $room = $this->room();
        Livewire::test(EditRoomStatus::class, ['record' => $room->id])
            ->fillForm(['status' => 'available'])->call('save')->assertHasFormErrors(['status']);
        Notification::assertNotified(Notification::make()->danger()->title('Room status was not changed')
            ->body('A supervisor must verify the latest turnaround checklist before this room can be marked ready. For cleaning rooms, complete the checklist under Tasks, then use Room Readiness Review → Verify & Release.')
            ->persistent());
        $this->assertSame('cleaning', $room->fresh()->status);
    }

    public function test_missing_checklist_can_be_recreated_then_completed_and_verified_without_repricing(): void
    {
        $manager = $this->manager(); $room = $this->room();
        Livewire::test(EditRoomStatus::class, ['record' => $room->id])->callAction('createReadinessChecklist');
        $task = StaffTask::where('room_id', $room->id)->sole();
        $this->assertSame('pending', $task->status);
        $this->assertSame('cleaning', $room->fresh()->status);
        $this->assertCount(4, $task->checklist_items);
        $task->update(['checklist_items' => array_map(fn ($item) => array_merge($item, ['done' => true]), $task->checklist_items), 'status' => 'completed']);
        app(RoomTurnoverService::class)->markReady($room, $manager);
        $this->assertSame('verified', $task->fresh()->status);
        $this->assertSame('available', $room->fresh()->status);
        $this->assertEquals(161250, $room->fresh()->price_per_night);
        $this->assertSame(['rooms/papilio.jpg'], json_decode($room->fresh()->getRawOriginal('images'), true));
    }

    public function test_recovery_rejects_duplicate_checklists_unauthorised_staff_and_an_active_guest(): void
    {
        $manager = $this->manager(); $room = $this->room();
        $service = app(RoomTurnoverService::class);
        $service->createMissingChecklist($room, $manager);
        try { $service->createMissingChecklist($room, $manager); $this->fail('Duplicate checklist accepted'); }
        catch (ValidationException) { $this->assertDatabaseCount('staff_tasks', 1); }
        $another = $this->room();
        try { $service->createMissingChecklist($another, User::factory()->create(['role' => User::ROLE_GUEST_RELATIONS])); $this->fail('Guest Relations created a recovery checklist'); }
        catch (ValidationException) { $this->assertDatabaseCount('staff_tasks', 1); }
        $stay = Booking::create(['user_id' => $manager->id, 'room_id' => $another->id, 'check_in' => today()->toDateString(),
            'check_out' => today()->addDay()->toDateString(), 'total_price' => 161250, 'payment_method' => 'pos']);
        $stay->forceFill(['checked_in_at' => now()])->save();
        try { $service->createMissingChecklist($another, $manager); $this->fail('Active guest was ignored'); }
        catch (ValidationException) { $this->assertDatabaseCount('staff_tasks', 1); }
    }

    public function test_readiness_confirm_shows_the_blocker_instead_of_silently_refreshing(): void
    {
        $manager = $this->manager(); $room = $this->room();
        Livewire::test(ListRoomReadiness::class)->assertSee('Missing checklist')
            ->callTableAction('release', $room);
        Notification::assertNotified(Notification::make()->danger()->persistent()->title('Room could not be released')
            ->body('Finish the latest checkout cleaning task before marking this suite ready. Use Open Checklist to finish the task, or Create Missing Checklist if none exists.'));
        $this->assertSame('cleaning', $room->fresh()->status);
        $task = app(RoomTurnoverService::class)->createMissingChecklist($room, $manager);
        Livewire::test(ListRoomReadiness::class)->assertSee('Pending')->callTableAction('release', $room);
        $this->assertSame('pending', $task->fresh()->status);
        $this->assertSame('cleaning', $room->fresh()->status);
    }

    public function test_readiness_confirm_releases_a_completed_room_and_records_the_reviewer(): void
    {
        $manager = $this->manager(); $room = $this->room();
        $task = app(RoomTurnoverService::class)->createMissingChecklist($room, $manager);
        $task->update(['checklist_items' => array_map(fn ($item) => array_merge($item, ['done' => true]), $task->checklist_items), 'status' => 'completed']);
        Livewire::test(ListRoomReadiness::class)->callTableAction('release', $room)->assertCanNotSeeTableRecords([$room]);
        Notification::assertNotified(Notification::make()->success()->title('Room verified and released')->body('Papilio is now available for check-in.'));
        $this->assertSame('available', $room->fresh()->status);
        $this->assertSame('verified', $task->fresh()->status);
        $this->assertEquals($manager->id, $task->fresh()->verified_by);
        $this->assertNotNull($task->fresh()->verified_at);
    }

    public function test_task_form_shows_checklist_error_and_retains_in_progress_when_items_are_unchecked(): void
    {
        $manager = $this->manager(); $room = $this->room();
        $task = app(RoomTurnoverService::class)->createMissingChecklist($room, $manager);
        $task->update(['status' => 'in_progress']);
        Livewire::test(EditStaffTask::class, ['record' => $task->id])
            ->fillForm(['status' => 'completed'])->call('save')->assertHasFormErrors(['checklist_items']);
        Notification::assertNotified(Notification::make()->danger()->persistent()->title('Task was not saved')
            ->body('Finish every cleaning checklist item. Switch on Done for every item before selecting Completed, then save again.'));
        $this->assertSame('in_progress', $task->fresh()->status);
        $this->assertNull($task->fresh()->completed_at);
    }

    public function test_actual_task_form_completion_then_readiness_confirmation_releases_the_room(): void
    {
        $manager = $this->manager(); $room = $this->room();
        $task = app(RoomTurnoverService::class)->createMissingChecklist($room, $manager);
        $task->update(['status' => 'in_progress']);
        Livewire::test(EditStaffTask::class, ['record' => $task->id])
            ->fillForm(fn (array $state) => ['status' => 'completed', 'checklist_items' => array_map(fn ($item) => array_merge($item, ['done' => true]), $state['checklist_items'])])
            ->call('save', false)->assertHasNoFormErrors();
        $this->assertSame('completed', $task->fresh()->status);
        $this->assertNotNull($task->fresh()->completed_at);
        Livewire::test(ListRoomReadiness::class)->callTableAction('release', $room)->assertCanNotSeeTableRecords([$room]);
        $this->assertSame('available', $room->fresh()->status);
        $this->assertSame('verified', $task->fresh()->status);
    }
}
