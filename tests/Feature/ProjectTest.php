<?php
// tests/Feature/ProjectTest.php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;
    private User $member;

    protected function setUp(): void
    {
        //todo:manage token hear
        parent::setUp();

        // Create users with different roles
        $this->admin = User::factory()->admin()->create();
        $this->manager = User::factory()->manager()->create();
        $this->member = User::factory()->member()->create();
    }

    // ============================================
    // LIST PROJECTS
    // ============================================

    public function test_admin_can_view_all_projects(): void
    {
        Project::factory(5)->create();

        $token = $this->admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/projects');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [],
                'meta' => [
                    'total',
                    'per_page',
                    'current_page',
                    'last_page',
                ],
            ]);
    }

    public function test_manager_can_view_only_their_projects(): void
    {
        // Projects owned by manager
        Project::factory(3)->create(['owner_id' => $this->manager->id]);
        // Projects owned by others
        Project::factory(5)->create();

        $token = $this->manager->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/projects');

        $response->assertStatus(200);

        // Should only see projects they own or are members of
        $projectIds = collect($response->json('data'))->pluck('id');
        $this->assertCount(3, $projectIds); // Only the ones they own
    }

    public function test_project_index_rejects_invalid_filters(): void
    {
        $token = $this->admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/projects?owner_id=invalid&sort_by=invalid&per_page=0');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['owner_id', 'sort_by', 'per_page']);
    }

    // ============================================
    // CREATE PROJECT
    // ============================================

    public function test_admin_can_create_project(): void
    {
        $token = $this->admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/projects', [
                'name' => 'New Project',
                'description' => 'Project description',
                'members' => [$this->member->id],
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Project created successfully',
            ]);

        $this->assertDatabaseHas('projects', [
            'name' => 'New Project',
            'owner_id' => $this->admin->id,
        ]);
    }

    public function test_manager_can_create_project(): void
    {
        $token = $this->manager->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/projects', [
                'name' => 'Manager Project',
                'description' => 'Description',
            ]);

        $response->assertStatus(201);
    }

    public function test_member_cannot_create_project(): void
    {
        $token = $this->member->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/projects', [
                'name' => 'Member Project',
                'description' => 'Description',
            ]);

        $response->assertStatus(403); // Forbidden
    }

    // ============================================
    // UPDATE PROJECT
    // ============================================

    public function test_owner_can_update_project(): void
    {
        $project = Project::factory()->create(['owner_id' => $this->manager->id]);

        $token = $this->manager->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/projects/{$project->id}", [
                'name' => 'Updated Name',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_member_cannot_update_project(): void
    {
        $project = Project::factory()->create(['owner_id' => $this->admin->id]);
        $project->members()->attach($this->member->id);

        $token = $this->member->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/projects/{$project->id}", [
                'name' => 'Hacked Name',
            ]);

        $response->assertStatus(403);
    }

    // ============================================
    // DELETE PROJECT
    // ============================================

    public function test_owner_can_delete_project(): void
    {
        $project = Project::factory()->create(['owner_id' => $this->manager->id]);

        $token = $this->manager->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/projects/{$project->id}");

        $response->assertStatus(200);

        $this->assertSoftDeleted($project);
    }

    public function test_admin_can_delete_any_project(): void
    {
        $project = Project::factory()->create(['owner_id' => $this->member->id]);

        $token = $this->admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/projects/{$project->id}");

        $response->assertStatus(200);

        $this->assertSoftDeleted($project);
    }

    // ============================================
    // RESTORE PROJECT
    // ============================================

    public function test_owner_can_restore_project(): void
    {
        $project = Project::factory()->create(['owner_id' => $this->manager->id]);
        $project->delete();

        $token = $this->manager->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/projects/{$project->id}/restore");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Project restored successfully',
            ]);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'deleted_at' => null,
        ]);
    }

    // ============================================
    // SEARCH & FILTER
    // ============================================

    public function test_can_search_projects_by_name(): void
    {
        Project::factory()->create(['name' => 'E-commerce Platform', 'owner_id' => $this->admin->id]);
        Project::factory()->create(['name' => 'Blog System', 'owner_id' => $this->admin->id]);

        $token = $this->admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/projects?search=E-commerce');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('E-commerce Platform', $response->json('data.0.name'));
    }

    public function test_can_filter_projects_by_owner(): void
    {
        Project::factory(3)->create(['owner_id' => $this->admin->id]);
        Project::factory(2)->create(['owner_id' => $this->manager->id]);

        $token = $this->admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/projects?owner_id={$this->admin->id}");

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }
}
