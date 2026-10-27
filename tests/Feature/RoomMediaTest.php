<?php

namespace Tests\Feature;

use App\Filament\Resources\RoomResource\Pages\CreateRoom;
use App\Filament\Resources\RoomResource\Pages\EditRoom;
use App\Filament\Resources\RoomResource\Pages\ListRooms;
use App\Filament\Resources\RoomResource\Widgets\RoomOverviewWidget;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class RoomMediaTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup public disk with mock files
        Storage::fake('public');

        Storage::disk('public')->put('rooms/test_image_1.jpg', 'fake-image-content-1');
        Storage::disk('public')->put('rooms/test_image_2.jpg', 'fake-image-content-2');
        Storage::disk('public')->put('rooms/test_video.mp4', 'fake-video-content');

        $this->adminUser = User::factory()->create([
            'role' => 'ipl_admin',
        ]);

        $this->room = Room::create([
            'name' => 'Atala Test Suite',
            'type' => 'Suite',
            'tag' => 'Exclusive Luxury',
            'description' => 'Test room description',
            'price_per_night' => 150000,
            'amenities' => ['wifi', 'tv', 'pool'],
            'images' => ['rooms/test_image_1.jpg', 'rooms/test_image_2.jpg'],
            'video_url' => 'rooms/test_video.mp4',
            'is_available' => true,
            'status' => 'available',
        ]);
    }

    public function test_room_media_mutators_sanitize_and_preserve_relative_storage_paths(): void
    {
        // 1. Storing paths with full URLs or /storage/ prefixes should be stripped to clean relative paths
        $this->room->images = [
            'http://192.168.8.199:8000/storage/rooms/test_image_1.jpg',
            '/storage/rooms/test_image_2.jpg',
            'storage/rooms/test_image_1.jpg',
            'rooms/test_image_2.jpg',
        ];
        $this->room->video_url = 'http://192.168.8.199:8000/storage/rooms/test_video.mp4';
        $this->room->save();

        $this->room->refresh();

        $this->assertSame(
            ['rooms/test_image_1.jpg', 'rooms/test_image_2.jpg', 'rooms/test_image_1.jpg', 'rooms/test_image_2.jpg'],
            json_decode($this->room->getRawOriginal('images'), true)
        );
        $this->assertSame('rooms/test_video.mp4', $this->room->getRawOriginal('video_url'));
    }

    public function test_filament_room_edit_form_hydrates_existing_media_with_same_origin_relative_urls(): void
    {
        $this->actingAs($this->adminUser);

        $test = Livewire::test(EditRoom::class, ['record' => $this->room->id]);

        $uploadedImages = $test->instance()->getFormUploadedFiles('data.images');
        $uploadedVideo = $test->instance()->getFormUploadedFiles('data.video_url');

        $this->assertNotEmpty($uploadedImages);
        $this->assertCount(2, $uploadedImages);

        foreach ($uploadedImages as $fileKey => $fileInfo) {
            $this->assertNotNull($fileInfo);
            $this->assertStringStartsWith('/storage/rooms/', $fileInfo['url']);
            $this->assertGreaterThan(0, $fileInfo['size']);
            $this->assertNotEmpty($fileInfo['name']);
        }

        $this->assertNotEmpty($uploadedVideo);
        $videoInfo = reset($uploadedVideo);
        $this->assertNotNull($videoInfo);
        $this->assertStringStartsWith('/storage/rooms/', $videoInfo['url']);
        $this->assertSame('test_video.mp4', $videoInfo['name']);
        $this->assertGreaterThan(0, $videoInfo['size']);
    }

    public function test_saving_room_edit_form_preserves_persisted_media_references(): void
    {
        $this->actingAs($this->adminUser);

        $initialImages = $this->room->getRawOriginal('images');
        $initialVideo = $this->room->getRawOriginal('video_url');

        $test = Livewire::test(EditRoom::class, ['record' => $this->room->id]);
        $test->call('save');
        $test->assertHasNoFormErrors();

        $this->room->refresh();

        $this->assertSame($initialImages, $this->room->getRawOriginal('images'));
        $this->assertSame($initialVideo, $this->room->getRawOriginal('video_url'));
    }

    public function test_api_rooms_endpoint_returns_absolute_accessible_media_urls(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $response = $this->getJson('/api/rooms');

        $response->assertStatus(200);

        $roomData = collect($response->json())->firstWhere('id', $this->room->id);
        $this->assertNotNull($roomData);

        // API should deliver absolute URLs for mobile clients
        foreach ($roomData['images'] as $imageUrl) {
            $this->assertStringStartsWith('http', $imageUrl);
            $this->assertStringContainsString('/storage/rooms/', $imageUrl);
        }

        $this->assertStringStartsWith('http', $roomData['video_url']);
        $this->assertStringContainsString('/storage/rooms/test_video.mp4', $roomData['video_url']);
    }

    public function test_list_rooms_page_renders_with_redesigned_columns_and_stats_widget(): void
    {
        $this->actingAs($this->adminUser);

        // 1. Verify the ListRooms page renders all new and upgraded columns
        $test = Livewire::test(ListRooms::class);

        $test->assertSuccessful();
        $test->assertCanRenderTableColumn('images');
        $test->assertCanRenderTableColumn('name');
        $test->assertCanRenderTableColumn('price_per_night');
        $test->assertCanRenderTableColumn('status');
        $test->assertCanRenderTableColumn('is_available');
        $test->assertCanRenderTableColumn('amenities');
        $test->assertCanSeeTableRecords([$this->room]);

        // 2. Verify the RoomOverviewWidget renders KPI stats correctly
        $widgetTest = Livewire::test(RoomOverviewWidget::class);
        $widgetTest->assertSuccessful();
        $widgetTest->assertSee('Total Accommodation Units');
        $widgetTest->assertSee('Ready for Check-in');
        $widgetTest->assertSee('Turnover & Maintenance');
        $widgetTest->assertSee('Current Occupancy');
    }

    public function test_list_rooms_quick_status_action_updates_operational_state(): void
    {
        $this->actingAs($this->adminUser);

        $test = Livewire::test(ListRooms::class);

        $test->callTableAction('quickStatus', $this->room, [
            'status' => 'cleaning',
            'housekeeping_notes' => 'Deep cleaning master bath after VIP checkout',
        ]);

        $this->room->refresh();

        $this->assertSame('cleaning', $this->room->status);
        $this->assertSame('Deep cleaning master bath after VIP checkout', $this->room->housekeeping_notes);
    }

    public function test_list_rooms_toggle_availability_persists(): void
    {
        $this->actingAs($this->adminUser);

        $this->assertTrue($this->room->is_available);

        $test = Livewire::test(ListRooms::class);
        $test->call('updateTableColumnState', 'is_available', (string) $this->room->id, false);

        $this->room->refresh();
        $this->assertFalse($this->room->is_available);
        $test->assertTableColumnStateSet('is_available', false, $this->room);
    }

    public function test_edit_room_page_renders_with_redesigned_sections_and_fields(): void
    {
        $this->actingAs($this->adminUser);

        $test = Livewire::test(EditRoom::class, ['record' => $this->room->id]);

        $test->assertSuccessful();
        $test->assertSee('Edit Atala Test Suite');
        $test->assertSee('Suite Identity & Story');
        $test->assertSee('Pricing & Commercial Terms');
        $test->assertSee('Operations & Housekeeping');
        $test->assertSee('Amenities & Premium Inclusions');
        $test->assertSee('Photography Gallery');
        $test->assertSee('Cinematic Video Walkthrough');
        $test->assertActionExists('view');
        $test->assertActionExists('delete');
    }

    public function test_edit_room_page_saves_operational_and_branding_fields_while_preserving_media(): void
    {
        $this->actingAs($this->adminUser);

        $initialImages = $this->room->getRawOriginal('images');
        $initialVideo = $this->room->getRawOriginal('video_url');

        $test = Livewire::test(EditRoom::class, ['record' => $this->room->id]);

        $test->fillForm([
            'tag' => 'Flagship Luxury Penthouse',
            'category_description' => 'Top floor executive suite with panoramic ocean views',
            'price_per_night' => 195000,
            'status' => 'reserved',
            'housekeeping_notes' => 'Reserved for corporate VIP delegation arriving Friday',
            'amenities' => ['wifi', 'tv', 'pool', 'wine'],
        ]);

        $test->call('save');
        $test->assertHasNoFormErrors();

        $this->room->refresh();

        $this->assertSame('Flagship Luxury Penthouse', $this->room->tag);
        $this->assertSame('Top floor executive suite with panoramic ocean views', $this->room->category_description);
        $this->assertEquals(195000, (float) $this->room->price_per_night);
        $this->assertSame('reserved', $this->room->status);
        $this->assertSame('Reserved for corporate VIP delegation arriving Friday', $this->room->housekeeping_notes);
        $this->assertSame(['wifi', 'tv', 'pool', 'wine'], $this->room->amenities);

        // Media references must remain intact
        $this->assertSame($initialImages, $this->room->getRawOriginal('images'));
        $this->assertSame($initialVideo, $this->room->getRawOriginal('video_url'));
    }

    public function test_create_room_page_renders_and_creates_new_suite(): void
    {
        $this->actingAs($this->adminUser);

        $test = Livewire::test(CreateRoom::class);

        $test->assertSuccessful();
        $test->assertSee('Add New Suite');

        $test->fillForm([
            'name' => 'Papilio Grand Suite',
            'type' => 'Suite',
            'tag' => 'Brand New Luxury Addition',
            'category_description' => 'Spacious suite with king bed and private terrace',
            'description' => 'Complete grand suite with modern fittings and city view',
            'price_per_night' => 220000,
            'is_available' => true,
            'status' => 'available',
            'housekeeping_notes' => 'Initial inventory inspection complete',
            'amenities' => ['wifi', 'pool', 'restaurant'],
        ]);

        $test->call('create');
        $test->assertHasNoFormErrors();

        $created = Room::where('name', 'Papilio Grand Suite')->first();
        $this->assertNotNull($created);
        $this->assertSame('Suite', $created->type);
        $this->assertEquals(220000, (float) $created->price_per_night);
        $this->assertTrue($created->is_available);
        $this->assertSame('available', $created->status);
    }
}
