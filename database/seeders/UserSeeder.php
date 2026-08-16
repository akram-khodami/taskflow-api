<?php
// database/seeders/UserSeeder.php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@taskflow.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        // Manager
        User::create([
            'name' => 'Manager User',
            'email' => 'manager@taskflow.com',
            'password' => Hash::make('password'),
            'role' => 'manager',
        ]);

        // Member
        User::create([
            'name' => 'Member User',
            'email' => 'member@taskflow.com',
            'password' => Hash::make('password'),
            'role' => 'member',
        ]);

        // 10 random users
        User::factory()->count(10)->create();
    }
}
