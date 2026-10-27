<?php

namespace Tests\Feature;

use App\Filament\Operations\Resources\RoomStatusResource;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomStatusRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_housekeeping_cannot_edit_room_status_record(): void
    {
        $room = new Room(['name' => 'Test Suite', 'status' => 'available']);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_STAFF]));

        $this->assertTrue(RoomStatusResource::canViewAny());
        $this->assertFalse(RoomStatusResource::canEdit($room));
    }

    public function test_mcl_supervisor_can_edit_room_status_record(): void
    {
        $room = new Room(['name' => 'Test Suite', 'status' => 'available']);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_DELIVERY_PARTNER]));

        $this->assertTrue(RoomStatusResource::canEdit($room));
    }
}
