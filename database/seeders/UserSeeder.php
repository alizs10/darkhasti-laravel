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

        $usernames = [
            'taher93',
            'ninaTech',
            'ali_dev',
            'saraFinance',
            'mehdiStudy',
            'armanCareer',
            'maryamHelp',
            'saharCode',
            'aminApp',
            'zahraEdu',
            'hosseinLaw',
            'nedaLife',
            'rezaNews',
            'yasinCrypto',
            'minaHealth',
            'arashMarket',
            'leilaPolicy',
            'parisaDesign',
            'hosseinGh',
            'farnoosh101',
        ];

        $users = collect($usernames)->map(function ($username) {
            return User::create([
                'username' => $username,
                'password' => bcrypt('password'),
            ]);
        });

        $this->command?->info('Created users: '.$users->count());
    }
}
