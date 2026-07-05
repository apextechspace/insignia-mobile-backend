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
                'images' => ['https://images.unsplash.com/photo-1596394516093-501ba68a0ba6?auto=format&fit=crop&w=800&q=80'],
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
                'images' => ['https://images.unsplash.com/photo-1598928506311-c55ded91a20c?auto=format&fit=crop&w=800&q=80'],
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
                'images' => ['https://images.unsplash.com/photo-1631049307264-da0ec9d70304?auto=format&fit=crop&w=800&q=80'],
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
                'images' => ['https://images.unsplash.com/photo-1618773928121-c32242e63f39?auto=format&fit=crop&w=800&q=80'],
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
                'images' => ['https://images.unsplash.com/photo-1590490360182-c33d57733427?auto=format&fit=crop&w=800&q=80'],
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
                'images' => ['https://images.unsplash.com/photo-1560448204-603b3fc33ddc?auto=format&fit=crop&w=800&q=80'],
                'is_available' => true,
            ],
        ];

        foreach ($rooms as $room) {
            Room::updateOrCreate(
                ['name' => $room['name']],
                $room
            );
        }
    }
}
