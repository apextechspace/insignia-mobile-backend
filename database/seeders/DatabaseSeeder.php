<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@insignia.com'],
            [
                'name' => 'Admin User',
                'password' => \Illuminate\Support\Facades\Hash::make('password123'),
                'role' => 'admin'
            ]
        );

        User::firstOrCreate(
            ['email' => 'user@insignia.com'],
            [
                'name' => 'Standard User',
                'password' => \Illuminate\Support\Facades\Hash::make('password123'),
                'role' => 'user'
            ]
        );

        $this->call([
            RoomSeeder::class,
            ServiceSeeder::class,
            ProductSeeder::class,
            OperationsSeeder::class,
        ]);
    }
}
