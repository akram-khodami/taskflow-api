<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_index_rejects_invalid_filters(): void
    {
        User::factory()->create();

        $response = $this->getJson('/api/v1/users?role=invalid&project_id=invalid&sort_by=invalid&per_page=0');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role', 'project_id', 'sort_by', 'per_page']);
    }
}
