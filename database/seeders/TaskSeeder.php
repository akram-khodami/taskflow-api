<?php
// database/seeders/TaskSeeder.php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    public function run(): void
    {
        $projects = Project::all();

        if ($projects->isEmpty()) {
            $this->command->warn('No projects found. Please run ProjectSeeder first.');
            return;
        }

        $users = User::all();

        foreach ($projects as $project) {
            // Get accessible users for this project (owner + members)
            $accessibleUsers = collect([$project->owner])->merge($project->members)->unique('id');

            if ($accessibleUsers->isEmpty()) {
                continue;
            }

            // Create 5-15 tasks per project
            $taskCount = rand(5, 15);

            Task::factory()
                ->count($taskCount)
                ->forProject($project)
                ->create()
                ->each(function (Task $task) use ($accessibleUsers) {
                    // Randomly assign to a project member or owner
                    $assignee = $accessibleUsers->random();
                    $task->assignee_id = $assignee->id;

                    // Set creator as a random project member
                    $creator = $accessibleUsers->random();
                    $task->created_by = $creator->id;

                    $task->save();
                });
        }

        // Create some specific tasks for testing
        $this->createSpecificTasks();

        $this->command->info('Tasks created successfully!');
    }

    private function createSpecificTasks(): void
    {
        // Get first project and its users
        $project = Project::first();
        if (!$project) {
            return;
        }

        $users = $project->members()->limit(3)->get();
        if ($users->isEmpty()) {
            $users = User::limit(3)->get();
        }

        // Create tasks with different statuses for My Tasks feature
        foreach ($users as $user) {
            // Backlog tasks
            Task::factory()
                ->count(2)
                ->forProject($project)
                ->assignedTo($user)
                ->withStatus('backlog')
                ->create(['created_by' => $user->id]);

            // In progress tasks
            Task::factory()
                ->count(3)
                ->forProject($project)
                ->assignedTo($user)
                ->withStatus('in_progress')
                ->create(['created_by' => $user->id]);

            // In review tasks
            Task::factory()
                ->count(1)
                ->forProject($project)
                ->assignedTo($user)
                ->withStatus('in_review')
                ->create(['created_by' => $user->id]);

            // Completed tasks
            Task::factory()
                ->count(2)
                ->forProject($project)
                ->assignedTo($user)
                ->completed()
                ->create(['created_by' => $user->id]);

            // Overdue tasks
            Task::factory()
                ->count(1)
                ->forProject($project)
                ->assignedTo($user)
                ->overdue()
                ->create(['created_by' => $user->id]);
        }
    }
}
