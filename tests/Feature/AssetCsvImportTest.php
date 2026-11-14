<?php

namespace Tests\Feature;

use App\Filament\Operations\Resources\AssetResource\Pages\ListAssets;
use App\Models\Asset;
use App\Models\Room;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class AssetCsvImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_preview_and_import_assets_without_overwriting_duplicates(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_DELIVERY_PARTNER]));
        Filament::setCurrentPanel(Filament::getPanel('operations'));
        $room = Room::create(['name' => 'Atala', 'type' => 'Suite', 'price_per_night' => 1000]);

        $page = Livewire::test(ListAssets::class)
            ->mountAction('importAssets')
            ->set('mountedActionsData.0.csv', [UploadedFile::fake()->createWithContent('assets.csv',
                "asset_code,name,category,room\nAST-01,Television,electronics,Atala\n"
            )])
            ->assertSee('1 new assets ready to import');
        $this->assertDatabaseCount('assets', 0);
        $page->callMountedAction()->assertHasNoActionErrors();
        $this->assertDatabaseHas('assets', ['asset_code' => 'AST-01', 'name' => 'Television', 'room_id' => $room->id]);

        Livewire::test(ListAssets::class)
            ->mountAction('importAssets')
            ->set('mountedActionsData.0.csv', [UploadedFile::fake()->createWithContent('assets.csv',
                "asset_code,name,category\nast-01,Replacement,electronics\nAST-02,Desk,furniture\n"
            )])
            ->assertSee('Duplicate asset tag ast-01')
            ->callMountedAction();
        $this->assertSame('Television', Asset::sole()->name);
    }

    public function test_staff_cannot_mount_the_import_action(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_STAFF]));
        Filament::setCurrentPanel(Filament::getPanel('operations'));

        $this->get(\App\Filament\Operations\Resources\AssetResource::getUrl(panel: 'operations'))->assertForbidden();
    }
}
