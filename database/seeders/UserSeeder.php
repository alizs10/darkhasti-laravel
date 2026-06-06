<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::query()->delete(); // optional: clean for re-seeding

        $users = collect(range(1, 10))->map(function ($i) {
            return User::create([
                'username' => 'user'.$i,
                'password' => bcrypt('password'), // simple for dev
            ]);
        });

        $this->command?->info('Created users: '.$users->count());
    }
}
