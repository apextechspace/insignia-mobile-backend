<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Room;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        // STANDARD ROOMS
        $standardDesc = 'Calm, ease, everyday elegance';
        
        $rooms = [
            [
                'name' => 'Atala',
                'type' => 'Standard Room',
                'tag' => 'Light & Restful',
                'category_description' => $standardDesc,
                'description' => "Light, restful space designed for ease and quiet comfort.\r\nSimple, balanced, and thoughtfully prepared.",
                'price_per_night' => 65000.00,
                'amenities' => ['wifi', 'tv', 'ac', 'coffee'],
                'images' => [
                    'rooms/atala_standard.jpg',
                    'rooms/Atala0.jpg',
                    'rooms/Atala_bathroom.jpg',
                ],
                'video_url' => 'rooms/entrance.mp4',
                'is_available' => true,
            ],
            [
                'name' => 'Junonia',
                'type' => 'Standard Room',
                'tag' => 'Calm & Composed',
                'category_description' => $standardDesc,
                'description' => "Calm and composed with a refined feel.\r\nDesigned for rest, clarity, and quiet retreat.",
                'price_per_night' => 65000.00,
                'amenities' => ['wifi', 'tv', 'ac', 'coffee'],
                'images' => [
                    'rooms/junonia_standard.jpg',
                    'rooms/space.jpeg',
                    'rooms/hallway.jpg',
                ],
                'video_url' => null,
                'is_available' => true,
            ],
            [
                'name' => 'Lyria',
                'type' => 'Executive Room',
                'tag' => 'Flexible Comfort',
                'category_description' => 'More presence, still understated',
                'description' => "Designed for comfort and flexibility.\r\nIdeal for extended stays and relaxed living.",
                'price_per_night' => 120000.00,
                'amenities' => ['wifi', 'tv', 'ac', 'kitchenette', 'desk'],
                'images' => [
                    'rooms/lyria_executive.jpg',
                    'rooms/space2.jpeg',
                    'rooms/space3.jpeg',
                ],
                'video_url' => null,
                'is_available' => true,
            ],
            [
                'name' => 'Evania',
                'type' => 'Executive Room',
                'tag' => 'Elevated Living',
                'category_description' => 'More presence, still understated',
                'description' => "A more settled and refined experience.\r\nDesigned for those who appreciate added comfort.",
                'price_per_night' => 120000.00,
                'amenities' => ['wifi', 'tv', 'ac', 'kitchenette', 'desk', 'sofa'],
                'images' => [
                    'rooms/evania_executive.jpg',
                    'rooms/space4.jpeg',
                    'rooms/space5.jpeg',
                ],
                'video_url' => null,
                'is_available' => true,
            ],
            [
                'name' => 'Morpho',
                'type' => 'Suite',
                'tag' => 'Distinctive Stay',
                'category_description' => 'Statement spaces with quiet luxury',
                'description' => "Distinctive space with character and presence.\r\nDesigned for a more immersive stay.",
                'price_per_night' => 250000.00,
                'amenities' => ['wifi', 'tv', 'ac', 'full_kitchen', 'living_room', 'balcony'],
                'images' => [
                    'rooms/morpho_suite.jpg',
                    'rooms/Morpho_suite0.jpg',
                    'rooms/morpho_suite000.jpg',
                    'rooms/morpho_suite0000.jpg',
                ],
                'video_url' => null,
                'is_available' => true,
            ],
            [
                'name' => 'Papilio',
                'type' => 'Suite',
                'tag' => 'Signature Experience',
                'category_description' => 'Statement spaces with quiet luxury',
                'description' => "A more private and considered experience.\r\nDesigned for comfort, calm, and ease.",
                'price_per_night' => 250000.00,
                'amenities' => ['wifi', 'tv', 'ac', 'full_kitchen', 'living_room', 'bath'],
                'images' => [
                    'rooms/papilio_suite.jpg',
                    'rooms/papilio_suite0.jpg',
                    'rooms/papilio_suite00.jpg',
                    'rooms/suite papilio.jpg',
                ],
                'video_url' => 'rooms/papilio.mp4',
                'is_available' => true,
            ],
        ];

        foreach ($rooms as $room) {
            // Bootstrap missing rooms without resetting live prices, media or readiness.
            Room::firstOrCreate(
                ['name' => $room['name']],
                $room
            );
        }
    }
}
