<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class QuickLoginUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Nathalie',
                'email' => 'nathalie@petcare.local',
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Léna',
                'email' => 'lena@petcare.local',
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Admin',
                'email' => 'admin@petcare.local',
                'password' => Hash::make('password'),
            ],
        ];

        foreach ($users as $userData) {
            User::firstOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }

        $this->command->info('3 utilisateurs créés : Nathalie, Léna, Admin');
    }
}
