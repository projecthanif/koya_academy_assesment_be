<?php

namespace Database\Seeders;

use App\Models\Slot;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Factories\Sequence;
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

        Slot::factory(10)
            ->sequence(fn (Sequence $sequence): array => [
                'provider' => 'Dr '.fake()->lastName(),
                'start' => now()->addDays($sequence->index + 1)->setTime(9, 0),
                'end' => now()->addDays($sequence->index + 1)->setTime(9, 30),
            ])
            ->create();
    }
}
