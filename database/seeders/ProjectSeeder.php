<?php
// database/seeders/ProjectSeeder.php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        // Get existing users or create them
        $users = User::all();

        if ($users->isEmpty()) {
            $this->command->warn('No users found. Please run UserSeeder first.');
            return;
        }

        // Create 15 projects with random owners
        Project::factory()
            ->count(15)
            ->create()
            ->each(function (Project $project) use ($users) {
                // Attach 2-5 random members (excluding owner)
                $availableUsers = $users->where('id', '!=', $project->owner_id);
                $memberCount = rand(2, min(5, $availableUsers->count()));
                $randomMembers = $availableUsers->random($memberCount);
                $project->members()->attach($randomMembers->pluck('id'));
            });

        $this->command->info('15 projects created with members.');
    }
}
