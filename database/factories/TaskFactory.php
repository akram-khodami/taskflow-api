<?php

// database/factories/TaskFactory.php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        $statuses = array_keys(Task::STATUSES);
        $priorities = array_keys(Task::PRIORITIES);

        return [
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraphs(2, true),
            'status' => $this->faker->randomElement($statuses),
            'priority' => $this->faker->randomElement($priorities),
            'due_date' => $this->faker->dateTimeBetween('now', '+30 days'),
            'project_id' => Project::factory(),
            'assignee_id' => User::factory(),
            'created_by' => User::factory(),
            'created_at' => $this->faker->dateTimeBetween('-2 months', 'now'),
            'updated_at' => now(),
        ];
    }

    /**
     * Configure the task with a specific project
     */
    public function forProject(Project $project): static
    {
        return $this->state(function (array $attributes) use ($project) {
            return [
                'project_id' => $project->id,
            ];
        });
    }

    /**
     * Configure the task with a specific assignee
     */
    public function assignedTo(User $user): static
    {
        return $this->state(function (array $attributes) use ($user) {
            return [
                'assignee_id' => $user->id,
            ];
        });
    }

    /**
     * Configure the task with a specific status
     */
    public function withStatus(string $status): static
    {
        return $this->state(function (array $attributes) use ($status) {
            return [
                'status' => $status,
            ];
        });
    }

    /**
     * Configure the task as overdue
     */
    public function overdue(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'due_date' => $this->faker->dateTimeBetween('-30 days', '-1 day'),
                'status' => $this->faker->randomElement(['backlog', 'in_progress', 'in_review']),
            ];
        });
    }

    /**
     * Configure the task as completed
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'done',
                'due_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            ];
        });
    }
}
