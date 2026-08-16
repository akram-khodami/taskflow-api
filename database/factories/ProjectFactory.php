<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->paragraph(),
            'owner_id' => User::factory(),
            'created_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'updated_at' => now(),
        ];
    }

    /**
     * Configure the factory to attach members after creation
     */
    public function withMembers(int $count = 3): static
    {
        return $this->afterCreating(function (Project $project) use ($count) {
            $members = User::factory($count)->create();
            $project->members()->attach($members->pluck('id'));
        });
    }

    /**
     * Attach specific users as members
     */
    public function withSpecificMembers(array $userIds): static
    {
        return $this->afterCreating(function (Project $project) use ($userIds) {
            $project->members()->attach($userIds);
        });
    }
}
