<?php

namespace Database\Seeders;

use App\Models\Slot;
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
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        Slot::factory(10)->create([
            'provider' => fake()->name(),
            'start' => fake()->dateTimeBetween('now', '+1 year'),
            'end' => fake()->dateTimeBetween('now', '+1 year'),
        ]);
    }
}
