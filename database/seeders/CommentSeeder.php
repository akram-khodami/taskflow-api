<?php

// database/seeders/CommentSeeder.php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

class CommentSeeder extends Seeder
{
    public function run(): void
    {
        $tasks = Task::with('project')->get();

        if ($tasks->isEmpty()) {
            $this->command->warn('No tasks found. Please run TaskSeeder first.');
            return;
        }

        foreach ($tasks as $task) {
            // Get users who have access to this task's project
            $project = $task->project;
            $accessibleUsers = collect([$project->owner])->merge($project->members)->unique('id');

            if ($accessibleUsers->isEmpty()) {
                continue;
            }

            // Create 2-8 comments per task
            $commentCount = rand(2, 8);

            $comments = Comment::factory()
                ->count($commentCount)
                ->forTask($task)
                ->create()
                ->each(function (Comment $comment) use ($accessibleUsers) {
                    // Assign random user from accessible users
                    $author = $accessibleUsers->random();
                    $comment->user_id = $author->id;
                    $comment->save();
                });

            // Add some replies (1-3 replies per task)
            $replyCount = rand(1, 3);
            $topComments = $comments->take(rand(2, min(5, $comments->count())));

            foreach ($topComments as $parentComment) {
                Comment::factory()
                    ->count(rand(1, 2))
                    ->replyTo($parentComment)
                    ->create()
                    ->each(function (Comment $reply) use ($accessibleUsers) {
                        $author = $accessibleUsers->random();
                        $reply->user_id = $author->id;
                        $reply->save();
                    });
            }
        }

        // Create some specific comments for testing
        $this->createSpecificComments();

        $this->command->info('Comments created successfully!');
    }

    private function createSpecificComments(): void
    {
        // Get first task and its project
        $task = Task::first();
        if (!$task) {
            return;
        }

        $project = $task->project;
        $users = $project->members()->limit(3)->get();

        if ($users->isEmpty()) {
            $users = User::limit(3)->get();
        }

        // Create a conversation thread
        $firstComment = Comment::factory()
            ->forTask($task)
            ->byUser($users->first())
            ->short()
            ->create([
                'body' => 'I think we need to reconsider the approach here.',
            ]);

        Comment::factory()
            ->replyTo($firstComment)
            ->byUser($users->get(1) ?? $users->first())
            ->create([
                'body' => 'Good point! What do you suggest instead?',
            ]);

        Comment::factory()
            ->replyTo($firstComment)
            ->byUser($users->get(2) ?? $users->first())
            ->create([
                'body' => 'How about using a simpler solution?',
            ]);

        // Add a final reply
        Comment::factory()
            ->replyTo($firstComment)
            ->byUser($users->first())
            ->create([
                'body' => 'Let\'s discuss this in the next meeting.',
            ]);
    }
}
