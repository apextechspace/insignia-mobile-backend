<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'name' => 'Midnight Oud Candle',
                'description' => 'Hand-poured luxury wax with notes of oud and spiced amber. This signature scent has been carefully curated to provide a warm, inviting atmosphere for our most discerning guests.',
                'price' => 65000.00,
                'category' => 'Aromatherapy',
                'image' => 'https://images.unsplash.com/photo-1513519245088-0e12902e5a38?auto=format&fit=crop&w=800&q=80',
                'stock' => 100,
            ],
            [
                'name' => 'Silk Sleep Mask',
                'description' => 'Experience the ultimate in restful sleep with our signature 100% pure mulberry silk sleep mask.',
                'price' => 45000.00,
                'category' => 'Textiles',
                'image' => 'https://images.unsplash.com/photo-1606103836293-0a063ee20566?auto=format&fit=crop&w=800&q=80',
                'stock' => 50,
            ],
            [
                'name' => 'Ceramic Essential Diffuser',
                'description' => 'Our artisan-crafted ceramic diffuser combines elegant design with ultrasonic technology.',
                'price' => 120000.00,
                'category' => 'Wellness',
                'image' => 'https://images.unsplash.com/photo-1602928321679-560bb453f190?auto=format&fit=crop&w=800&q=80',
                'stock' => 30,
            ],
            [
                'name' => 'Handcrafted Vase',
                'description' => 'Artisan ceramic vase, perfect for minimalist interiors.',
                'price' => 85000.00,
                'category' => 'Decor',
                'image' => 'https://images.unsplash.com/photo-1578500494198-246f612d3b37?auto=format&fit=crop&w=500&q=80',
                'stock' => 15,
            ],
            [
                'name' => 'Plush Velvet Throw',
                'description' => 'Heavyweight velvet throw for unparalleled comfort.',
                'price' => 180000.00,
                'category' => 'Textiles',
                'image' => 'https://images.unsplash.com/photo-1583951268689-d971511bb746?auto=format&fit=crop&w=500&q=80',
                'stock' => 25,
            ],
             [
                'name' => 'Signature Reed Diffuser',
                'description' => 'Long-lasting fragrance for your residence.',
                'price' => 55000.00,
                'category' => 'Aromatherapy',
                'image' => 'https://images.unsplash.com/photo-1608528570277-43f9bd49078b?auto=format&fit=crop&w=500&q=80',
                'stock' => 100,
            ],
            // DINING ITEMS (NEW)
            [
                'name' => 'Full English Breakfast',
                'description' => 'Eggs, bacon, sausages, beans, tomatoes, and toast.',
                'price' => 7500.00,
                'category' => 'Dining',
                'image' => 'https://images.unsplash.com/photo-1533089860892-a7c6f0a88666?auto=format&fit=crop&w=800&q=80',
                'stock' => 50,
            ],
            [
                'name' => 'Continental Breakfast',
                'description' => 'Fresh pastries, fruit, yogurt, and coffee.',
                'price' => 5000.00,
                'category' => 'Dining',
                'image' => 'https://images.unsplash.com/photo-1519915028121-7d3463d20b13?auto=format&fit=crop&w=800&q=80',
                'stock' => 50,
            ],
            [
                'name' => 'Garden Salad',
                'description' => 'Fresh seasonal greens with vinaigrette.',
                'price' => 4500.00,
                'category' => 'Dining',
                'image' => 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?auto=format&fit=crop&w=800&q=80',
                'stock' => 30,
            ],
            [
                'name' => 'Seasonal Fruit Platter',
                'description' => 'A selection of fresh tropical and seasonal fruits.',
                'price' => 4000.00,
                'category' => 'Dining',
                'image' => 'https://images.unsplash.com/photo-1619566636858-adf3ef46400b?auto=format&fit=crop&w=800&q=80',
                'stock' => 30,
            ],
            [
                'name' => 'Premium Fruit Juice',
                'description' => 'Freshly squeezed orange, pineapple, or apple juice.',
                'price' => 2500.00,
                'category' => 'Dining',
                'image' => 'https://images.unsplash.com/photo-1613478223719-2ab802602423?auto=format&fit=crop&w=800&q=80',
                'stock' => 100,
            ],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(
                ['name' => $product['name']],
                $product
            );
        }
    }
}
