<?php

// tests/Feature/TaskTest.php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;
    private User $member;
    private User $member2;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        // Create users
        $this->admin = User::factory()->admin()->create();
        $this->manager = User::factory()->manager()->create();
        $this->member = User::factory()->member()->create();
        $this->member2 = User::factory()->member()->create();

        // Create project with owner and members
        $this->project = Project::factory()->create(['owner_id' => $this->manager->id]);
        $this->project->members()->attach([
            $this->member->id,
            $this->member2->id,
        ]);
    }

    // ============================================
    // CREATE TASK
    // ============================================

    public function test_manager_can_create_task_in_their_project(): void
    {
        $token = $this->manager->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/projects/{$this->project->id}/tasks", [
                'title' => 'New Task',
                'description' => 'Task description',
                'status' => 'backlog',
                'priority' => 'high',
                'due_date' => '2026-09-01',
                'assignee_id' => $this->member->id,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Task created successfully',
            ]);

        $this->assertDatabaseHas('tasks', [
            'title' => 'New Task',
            'project_id' => $this->project->id,
            'assignee_id' => $this->member->id,
        ]);
    }

    public function test_member_can_create_task_in_their_project(): void
    {
        $token = $this->member->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/projects/{$this->project->id}/tasks", [
                'title' => 'Member Task',
                'description' => 'Description',
                'priority' => 'medium',
            ]);

        $response->assertStatus(201);
    }

    public function test_user_not_in_project_cannot_create_task(): void
    {
        $outsider = User::factory()->member()->create();
        $token = $outsider->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/projects/{$this->project->id}/tasks", [
                'title' => 'Hacked Task',
            ]);

        $response->assertStatus(403);
    }

    // ============================================
    // VIEW TASKS
    // ============================================

    public function test_user_can_view_tasks_in_their_project(): void
    {
        Task::factory(5)->forProject($this->project)->create();

        $token = $this->member->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/projects/{$this->project->id}/tasks");

        $response->assertStatus(200);
        $this->assertCount(5, $response->json('data'));
    }

    public function test_user_can_filter_tasks_by_status(): void
    {
        Task::factory(3)->forProject($this->project)->withStatus('backlog')->create();
        Task::factory(2)->forProject($this->project)->withStatus('in_progress')->create();

        $token = $this->member->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/projects/{$this->project->id}/tasks?status=backlog");

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
        $this->assertEquals('backlog', $response->json('data.0.status'));
    }

    public function test_user_can_search_tasks_by_title(): void
    {
        Task::factory()->forProject($this->project)->create(['title' => 'Fix payment bug']);
        Task::factory()->forProject($this->project)->create(['title' => 'Design database schema']);

        $token = $this->member->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/projects/{$this->project->id}/tasks?search=payment");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Fix payment bug', $response->json('data.0.title'));
    }

    // ============================================
    // UPDATE TASK
    // ============================================

    public function test_assignee_can_update_their_task(): void
    {
        $task = Task::factory()
            ->forProject($this->project)
            ->assignedTo($this->member)
            ->create();

        $token = $this->member->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/tasks/{$task->id}", [
                'title' => 'Updated Title',
                'description' => 'Updated description',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Updated Title',
        ]);
    }

    public function test_member_cannot_update_task_assigned_to_other(): void
    {
        $task = Task::factory()
            ->forProject($this->project)
            ->assignedTo($this->member2)
            ->create();

        $token = $this->member->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/tasks/{$task->id}", [
                'title' => 'Hacked Title',
            ]);

        $response->assertStatus(403);
    }

    // ============================================
    // UPDATE TASK STATUS
    // ============================================

    public function test_assignee_can_update_task_status(): void
    {
        $task = Task::factory()
            ->forProject($this->project)
            ->assignedTo($this->member)
            ->withStatus('backlog')
            ->create();

        $token = $this->member->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/tasks/{$task->id}/status", [
                'status' => 'in_progress',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_manager_can_update_task_status_for_any_task(): void
    {
        $task = Task::factory()
            ->forProject($this->project)
            ->assignedTo($this->member)
            ->withStatus('in_progress')
            ->create();

        $token = $this->manager->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/tasks/{$task->id}/status", [
                'status' => 'done',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => 'done',
        ]);
    }

    // ============================================
    // DELETE TASK
    // ============================================

    //todo:remove if poilcy changed
    // public function test_assignee_can_delete_their_task(): void
    // {
    //     $task = Task::factory()
    //         ->forProject($this->project)
    //         ->assignedTo($this->member)
    //         ->create();

    //     $token = $this->member->createToken('auth_token')->plainTextToken;

    //     $response = $this->withHeader('Authorization', "Bearer {$token}")
    //         ->deleteJson("/api/v1/tasks/{$task->id}");

    //     $response->assertStatus(200);
    //     $this->assertSoftDeleted($task);
    // }

    public function test_manager_can_delete_any_task_in_their_project(): void
    {
        $task = Task::factory()
            ->forProject($this->project)
            ->assignedTo($this->member)
            ->create();

        $token = $this->manager->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/tasks/{$task->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted($task);
    }

    public function test_member_cannot_delete_task_not_assigned_to_them(): void
    {
        $task = Task::factory()
            ->forProject($this->project)
            ->assignedTo($this->member2)
            ->create();

        $token = $this->member->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/tasks/{$task->id}");

        $response->assertStatus(403);
    }

    // ============================================
    // MY TASKS DASHBOARD
    // ============================================

    public function test_user_can_view_their_assigned_tasks(): void
    {
        Task::factory(3)->assignedTo($this->member)->create();
        Task::factory(2)->assignedTo($this->member2)->create();

        $token = $this->member->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/my-tasks');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data.tasks.data'));
        $this->assertEquals(3, $response->json('data.statistics.total'));
    }

    public function test_my_tasks_shows_status_statistics(): void
    {
        Task::factory(2)->assignedTo($this->member)->withStatus('backlog')->create();
        Task::factory(3)->assignedTo($this->member)->withStatus('in_progress')->create();
        Task::factory(1)->assignedTo($this->member)->withStatus('done')->create();

        $token = $this->member->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/my-tasks');

        $response->assertStatus(200);
        $this->assertEquals(6, $response->json('data.statistics.total'));
        $this->assertEquals(2, $response->json('data.statistics.by_status.backlog'));
        $this->assertEquals(3, $response->json('data.statistics.by_status.in_progress'));
        $this->assertEquals(1, $response->json('data.statistics.by_status.done'));
    }
}
