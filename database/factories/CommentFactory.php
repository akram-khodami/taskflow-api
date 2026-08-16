<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommentFactory extends Factory
{
    protected $model = Comment::class;

    public function definition(): array
    {
        return [
            'body' => $this->faker->paragraphs(rand(1, 3), true),
            'task_id' => Task::factory(),
            'user_id' => User::factory(),
            'parent_id' => null,
            'created_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'updated_at' => now(),
        ];
    }

    /**
     * Configure the comment for a specific task
     */
    public function forTask(Task $task): static
    {
        return $this->state(function (array $attributes) use ($task) {
            return [
                'task_id' => $task->id,
            ];
        });
    }

    /**
     * Configure the comment with a specific author
     */
    public function byUser(User $user): static
    {
        return $this->state(function (array $attributes) use ($user) {
            return [
                'user_id' => $user->id,
            ];
        });
    }

    /**
     * Configure the comment as a reply to another comment
     */
    public function replyTo(Comment $parent): static
    {
        return $this->state(function (array $attributes) use ($parent) {
            return [
                'parent_id' => $parent->id,
                'task_id' => $parent->task_id,
            ];
        });
    }

    /**
     * Create a short comment
     */
    public function short(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'body' => $this->faker->sentence(),
            ];
        });
    }

    /**
     * Create a long comment
     */
    public function long(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'body' => $this->faker->paragraphs(5, true),
            ];
        });
    }
}
