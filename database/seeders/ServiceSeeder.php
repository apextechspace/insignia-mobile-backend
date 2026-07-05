<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Service;
use App\Models\ServiceVariant;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        // 1. WELLNESS
        $wellness = Service::updateOrCreate(
            ['name' => 'Massage & Spa'],
            [
                'category' => 'Wellness',
                'type' => 'booking',
                'description' => 'Therapeutic massage and spa treatments.',
                'requires_variant' => true,
                'image' => 'https://images.unsplash.com/photo-1544161515-4ae6ce6fe858?auto=format&fit=crop&w=800&q=80',
                'is_active' => true,
            ]
        );
        $this->createVariants($wellness, [
            ['name' => 'In-room Massage', 'price' => 35000.00, 'duration' => 60],
            ['name' => 'Spa Treatment', 'price' => 45000.00, 'duration' => 90],
        ]);

        $pemf = Service::updateOrCreate(
            ['name' => 'PEMF Therapy'],
            [
                'category' => 'Wellness',
                'type' => 'booking',
                'description' => 'Pulsed Electromagnetic Field Therapy for recovery and wellness.',
                'requires_variant' => true,
                'image' => 'https://images.unsplash.com/photo-1519824141121-b97674399871?auto=format&fit=crop&w=800&q=80',
                'is_active' => true,
            ]
        );
        $this->createVariants($pemf, [
            ['name' => '30 Minutes', 'price' => 15000.00, 'duration' => 30],
            ['name' => '45 Minutes', 'price' => 20000.00, 'duration' => 45],
            ['name' => '60 Minutes', 'price' => 25000.00, 'duration' => 60],
        ]);

        Service::updateOrCreate(['name' => 'Outdoor Spa Sessions'], ['category' => 'Wellness', 'type' => 'booking', 'price' => 50000.00, 'is_active' => true]);
        Service::updateOrCreate(['name' => 'Medical On-Call'], ['category' => 'Wellness', 'type' => 'request', 'price' => 50000.00, 'is_active' => true]);

        // 2. EXPERIENCES
        Service::updateOrCreate(['name' => 'City Tour'], ['category' => 'Experiences', 'type' => 'booking', 'price' => 75000.00, 'is_active' => true]);
        Service::updateOrCreate(['name' => 'Professional Photography'], ['category' => 'Experiences', 'type' => 'booking', 'price' => 60000.00, 'is_active' => true]);
        Service::updateOrCreate(['name' => 'Beach / Outdoor Activities'], ['category' => 'Experiences', 'type' => 'booking', 'price' => 120000.00, 'is_active' => true]);
        Service::updateOrCreate(['name' => 'Room Décor (Birthday/Anniversary)'], ['category' => 'Experiences', 'type' => 'request', 'price' => 150000.00, 'is_active' => true]);
        Service::updateOrCreate(['name' => 'Theme Parks'], ['category' => 'Experiences', 'type' => 'booking', 'price' => 100000.00, 'is_active' => true]);
        Service::updateOrCreate(['name' => 'Event Reservations'], ['category' => 'Experiences', 'type' => 'request', 'price' => 25000.00, 'is_active' => true]);

        // 3. CONCIERGE
        $shopping = Service::updateOrCreate(
            ['name' => 'Personal Shopping'],
            [
                'category' => 'Concierge',
                'type' => 'booking',
                'description' => 'Personal shopping assistance in the city.',
                'requires_variant' => true,
                'image' => 'https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&w=800&q=80',
                'is_active' => true,
            ]
        );
        $this->createVariants($shopping, [
            ['name' => '1 Hour', 'price' => 15000.00, 'duration' => 60],
            ['name' => '2 Hours', 'price' => 25000.00, 'duration' => 120],
            ['name' => '4 Hours', 'price' => 45000.00, 'duration' => 240],
            ['name' => '6 Hours', 'price' => 60000.00, 'duration' => 360],
        ]);

        Service::updateOrCreate(['name' => 'Laundry'], ['category' => 'Concierge', 'type' => 'request', 'price' => 10000.00, 'is_active' => true]);
        Service::updateOrCreate(['name' => 'Outdoor Dining Reservations'], ['category' => 'Concierge', 'type' => 'request', 'price' => 5000.00, 'is_active' => true]);
        Service::updateOrCreate(['name' => 'Business Support (Meeting Rooms/Courier)'], ['category' => 'Concierge', 'type' => 'request', 'price' => 20000.00, 'is_active' => true]);
        Service::updateOrCreate(['name' => 'Errands (Pickups/Deliveries/Gift Sourcing)'], ['category' => 'Concierge', 'type' => 'request', 'price' => 15000.00, 'is_active' => true]);

        // 4. WORKSPACE
        Service::updateOrCreate(['name' => 'Hot Desks (1–4 people)'], ['category' => 'Workspace', 'type' => 'reservation', 'price' => 15000.00, 'is_active' => true]);
        Service::updateOrCreate(['name' => 'Meeting Rooms (2–5 people)'], ['category' => 'Workspace', 'type' => 'reservation', 'price' => 45000.00, 'is_active' => true]);
        Service::updateOrCreate(['name' => 'F&B (Food & Beverage)'], ['category' => 'Workspace', 'type' => 'request', 'price' => 10000.00, 'is_active' => true]);
        Service::updateOrCreate(['name' => 'Reprographic Services'], ['category' => 'Workspace', 'type' => 'request', 'price' => 5000.00, 'is_active' => true]);

        // 5. INTERIORS
        Service::updateOrCreate(['name' => 'New Builds'], ['category' => 'Interiors', 'type' => 'request', 'price' => 0.00, 'is_active' => true]);
        Service::updateOrCreate(['name' => 'Refurbishment'], ['category' => 'Interiors', 'type' => 'request', 'price' => 0.00, 'is_active' => true]);
        Service::updateOrCreate(['name' => 'Interior Design & Décor'], ['category' => 'Interiors', 'type' => 'request', 'price' => 0.00, 'is_active' => true]);
        Service::updateOrCreate(['name' => 'Landscaping'], ['category' => 'Interiors', 'type' => 'request', 'price' => 0.00, 'is_active' => true]);

        // 6. DINING
        $breakfastService = Service::updateOrCreate(
            ['name' => 'Inhouse (Breakfast)'],
            [
                'category' => 'Dining',
                'type' => 'order',
                'description' => 'Enjoy a delicious in-house breakfast.',
                'requires_variant' => false,
                'image' => 'https://images.unsplash.com/photo-1533089860892-a7c6f0a88666?auto=format&fit=crop&w=800&q=80',
                'is_active' => true,
            ]
        );

        // Find existing service by name or create a new one
        $refreshmentService = Service::where('name', 'LIKE', '%Light Refreshment%')->first();
        
        if ($refreshmentService) {
            $refreshmentService->update([
                'name' => 'Inhouse (Light Refreshment)',
                'category' => 'Dining',
                'type' => 'order',
                'description' => 'Light snacks and refreshments.',
                'is_active' => true,
            ]);
        } else {
            $refreshmentService = Service::create([
                'name' => 'Inhouse (Light Refreshment)',
                'category' => 'Dining',
                'type' => 'order',
                'description' => 'Light snacks and refreshments.',
                'requires_variant' => false,
                'image' => 'https://images.unsplash.com/photo-1495474472204-51e24a520c6b?auto=format&fit=crop&w=800&q=80',
                'is_active' => true,
            ]);
        }

        $restaurant1 = Service::updateOrCreate(
            ['name' => 'Restaurant 1'],
            [
                'category' => 'Dining',
                'type' => 'order',
                'description' => 'Fine dining experience.',
                'requires_variant' => false,
                'image' => 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=800&q=80',
                'is_active' => true,
            ]
        );

        $restaurant2 = Service::updateOrCreate(
            ['name' => 'Restaurant 2'],
            [
                'category' => 'Dining',
                'type' => 'order',
                'description' => 'Local and intercontinental dishes.',
                'requires_variant' => false,
                'image' => 'https://images.unsplash.com/photo-1552566626-52f8b828add9?auto=format&fit=crop&w=800&q=80',
                'is_active' => true,
            ]
        );

        $restaurant3 = Service::updateOrCreate(
            ['name' => 'Restaurant 3'],
            [
                'category' => 'Dining',
                'type' => 'order',
                'description' => 'Exotic Asian cuisine.',
                'requires_variant' => false,
                'image' => 'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?auto=format&fit=crop&w=800&q=80',
                'is_active' => true,
            ]
        );

        $restaurant4 = Service::updateOrCreate(
            ['name' => 'Restaurant 4'],
            [
                'category' => 'Dining',
                'type' => 'order',
                'description' => 'Casual dining and burgers.',
                'requires_variant' => false,
                'image' => 'https://images.unsplash.com/photo-1550547660-d9450f859349?auto=format&fit=crop&w=800&q=80',
                'is_active' => true,
            ]
        );

        // Seed Menu Categories linked to Services
        $breakfastCat = \App\Models\MenuCategory::updateOrCreate(
            ['name' => 'Breakfast Options', 'service_id' => $breakfastService->id],
            ['is_active' => true]
        );

        $snacksCat = \App\Models\MenuCategory::updateOrCreate(
            ['name' => 'Snacks & Bites', 'service_id' => $refreshmentService->id],
            ['is_active' => true]
        );

        $drinksCat = \App\Models\MenuCategory::updateOrCreate(
            ['name' => 'Cold Beverages', 'service_id' => $refreshmentService->id],
            ['is_active' => true]
        );

        $riceCat = \App\Models\MenuCategory::updateOrCreate(
            ['name' => 'Rice Specialties', 'service_id' => $restaurant1->id],
            ['is_active' => true]
        );

        // Seed Menu Items for Light Refreshments (Snacks & Bites)
        \App\Models\MenuItem::updateOrCreate(
            ['name' => 'Club Sandwich'],
            [
                'menu_category_id' => $snacksCat->id,
                'description' => 'Toasted bread with chicken, egg, lettuce, and mayo, served with fries',
                'price' => 4500.00,
                'is_available' => true,
                'image' => 'https://images.unsplash.com/photo-1528735602780-2552fd46c7af?auto=format&fit=crop&w=800&q=80'
            ]
        );

        \App\Models\MenuItem::updateOrCreate(
            ['name' => 'Chicken Wings (6pcs)'],
            [
                'menu_category_id' => $snacksCat->id,
                'description' => 'Spicy or BBQ glazed chicken wings',
                'price' => 3800.00,
                'is_available' => true,
                'image' => 'https://images.unsplash.com/photo-1527477396000-e27163b481c2?auto=format&fit=crop&w=800&q=80'
            ]
        );

        \App\Models\MenuItem::updateOrCreate(
            ['name' => 'French Fries'],
            [
                'menu_category_id' => $snacksCat->id,
                'description' => 'Crispy golden potato fries served with ketchup',
                'price' => 2000.00,
                'is_available' => true,
                'image' => 'https://images.unsplash.com/photo-1573080496219-bb080dd4f877?auto=format&fit=crop&w=800&q=80'
            ]
        );

        // Seed Menu Items for Light Refreshments (Cold Beverages)
        \App\Models\MenuItem::updateOrCreate(
            ['name' => 'Assorted Soda'],
            [
                'menu_category_id' => $drinksCat->id,
                'description' => 'Coke, Fanta, Sprite, or Pepsi (35cl)',
                'price' => 800.00,
                'is_available' => true,
                'image' => 'https://images.unsplash.com/photo-1622483767028-3f66f32aef97?auto=format&fit=crop&w=800&q=80'
            ]
        );

        \App\Models\MenuItem::updateOrCreate(
            ['name' => 'Fresh Orange Juice'],
            [
                'menu_category_id' => $drinksCat->id,
                'description' => '100% natural freshly squeezed orange juice',
                'price' => 2500.00,
                'is_available' => true,
                'image' => 'https://images.unsplash.com/photo-1613478223719-2ab802602423?auto=format&fit=crop&w=800&q=80'
            ]
        );

        // Seed existing items for Restaurant 1
        \App\Models\MenuItem::updateOrCreate(
            ['name' => 'Fiesta Fried Rice'],
            [
                'menu_category_id' => $riceCat->id,
                'description' => 'Basmati fiesta fried rice with veggies',
                'price' => 2000.00,
                'is_available' => true,
                'image' => 'https://images.unsplash.com/photo-1627308595229-7830a5c91f9f?auto=format&fit=crop&w=800&q=80'
            ]
        );

        \App\Models\MenuItem::updateOrCreate(
            ['name' => 'Asun Fried Rice'],
            [
                'menu_category_id' => $riceCat->id,
                'description' => 'Delicious basmati rice garnished with some smokey peppered asun',
                'price' => 3600.00,
                'is_available' => true,
                'image' => 'https://images.unsplash.com/photo-1603133872878-684f208fb84b?auto=format&fit=crop&w=800&q=80'
            ]
        );

        \App\Models\MenuItem::updateOrCreate(
            ['name' => 'Special Fried Rice'],
            [
                'menu_category_id' => $riceCat->id,
                'description' => 'Stir-fried basmati rice with shredded beef and vegetables',
                'price' => 3950.00,
                'is_available' => true,
                'image' => 'https://images.unsplash.com/photo-1512058564366-18510be2db19?auto=format&fit=crop&w=800&q=80'
            ]
        );

        // 7. TRANSPORT
        $transport = Service::updateOrCreate(
            ['name' => 'Airport Pickup & Drop-off'],
            [
                'category' => 'Transport',
                'type' => 'booking',
                'description' => 'Executive airport transfer in a premium vehicle.',
                'requires_variant' => true,
                'image' => 'https://images.unsplash.com/photo-1511192336575-5a79af67a629?auto=format&fit=crop&w=800&q=80',
                'is_active' => true,
            ]
        );
        $this->createVariants($transport, [
            ['name' => 'Pickup (Airport → Location)', 'price' => 35000.00],
            ['name' => 'Drop-off (Location → Airport)', 'price' => 35000.00],
            ['name' => 'Round Trip', 'price' => 60000.00],
        ]);

        Service::updateOrCreate(['name' => 'Chauffeur Services'], ['category' => 'Transport', 'type' => 'booking', 'price' => 85000.00, 'is_active' => true]);
    }

    private function createVariants(Service $service, array $variants)
    {
        foreach ($variants as $v) {
            ServiceVariant::updateOrCreate(
                ['service_id' => $service->id, 'name' => $v['name']],
                ['price' => $v['price'], 'duration_minutes' => $v['duration'] ?? null]
            );
        }
    }
}
