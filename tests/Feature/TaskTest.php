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
                'due_date' => now()->toDateString(),
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

    public function test_member_cannot_create_task_in_their_project(): void
    {
        $token = $this->member->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/projects/{$this->project->id}/tasks", [
                'title' => 'Member Task',
                'description' => 'Description',
                'priority' => 'medium',
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('tasks', ['title' => 'Member Task']);
    }

    public function test_manager_cannot_create_task_in_an_unrelated_project(): void
    {
        $otherManager = User::factory()->manager()->create();
        $unrelatedProject = Project::factory()->create(['owner_id' => $otherManager->id]);
        $token = $this->manager->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/projects/{$unrelatedProject->id}/tasks", [
                'title' => 'Unauthorized Task',
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('tasks', ['title' => 'Unauthorized Task']);
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

    public function test_task_assignee_must_belong_to_the_project_when_creating(): void
    {
        $outsider = User::factory()->member()->create();
        $token = $this->manager->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/projects/{$this->project->id}/tasks", [
                'title' => 'Invalid assignment',
                'assignee_id' => $outsider->id,
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors('assignee_id');
        $this->assertDatabaseMissing('tasks', ['title' => 'Invalid assignment']);
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
    }

    public function test_user_can_filter_tasks_by_status(): void
    {
        Task::factory(3)->forProject($this->project)->withStatus('backlog')->create();
        Task::factory(2)->forProject($this->project)->withStatus('in_progress')->create();

        $token = $this->member->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/projects/{$this->project->id}/tasks?status=backlog");

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
        $this->assertSame(
            ['backlog', 'backlog', 'backlog'],
            array_column($response->json('data'), 'status'),
        );
    }

    public function test_task_index_rejects_invalid_filters(): void
    {
        $token = $this->member->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/projects/{$this->project->id}/tasks?status=invalid&per_page=0");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status', 'per_page']);
    }

    public function test_user_can_search_tasks_by_title(): void
    {
        Task::factory()->forProject($this->project)->create(['title' => 'Fix payment bug']);
        Task::factory()->forProject($this->project)->create(['title' => 'Design database schema']);

        $token = $this->member->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/projects/{$this->project->id}/tasks?search=payment");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Fix payment bug', $response->json('data.0.title'));
    }

    public function test_task_index_paginates_results_and_returns_pagination_metadata(): void
    {
        Task::factory(5)->forProject($this->project)->create();
        $token = $this->member->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/projects/{$this->project->id}/tasks?per_page=2&page=2");

        $response->assertOk()
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.last_page', 3);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_task_index_filters_by_priority_and_assignee(): void
    {
        Task::factory()->forProject($this->project)->assignedTo($this->member)
            ->create(['priority' => 'urgent', 'title' => 'Matching task']);
        Task::factory()->forProject($this->project)->assignedTo($this->member2)
            ->create(['priority' => 'urgent', 'title' => 'Different assignee']);
        Task::factory()->forProject($this->project)->assignedTo($this->member)
            ->create(['priority' => 'low', 'title' => 'Different priority']);
        $token = $this->member->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/projects/{$this->project->id}/tasks?priority=urgent&assignee={$this->member->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Matching task', $response->json('data.0.title'));
    }

    public function test_due_today_is_not_overdue_but_yesterday_is(): void
    {
        Task::factory()->forProject($this->project)->create([
            'title' => 'Due today',
            'due_date' => today()->toDateString(),
            'status' => 'in_progress',
        ]);
        Task::factory()->forProject($this->project)->create([
            'title' => 'Overdue task',
            'due_date' => today()->subDay()->toDateString(),
            'status' => 'in_progress',
        ]);
        $token = $this->member->createToken('auth_token')->plainTextToken;

        $allTasks = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/projects/{$this->project->id}/tasks");
        $allTasks->assertOk();
        $this->assertFalse(collect($allTasks->json('data'))
            ->firstWhere('title', 'Due today')['is_overdue']);

        $overdueTasks = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/projects/{$this->project->id}/tasks?overdue=true");
        $overdueTasks->assertOk();
        $this->assertCount(1, $overdueTasks->json('data'));
        $this->assertSame('Overdue task', $overdueTasks->json('data.0.title'));
        $this->assertTrue($overdueTasks->json('data.0.is_overdue'));
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

    public function test_task_assignee_must_belong_to_the_project_when_updating(): void
    {
        $task = Task::factory()->forProject($this->project)->assignedTo($this->member)->create();
        $outsider = User::factory()->member()->create();
        $token = $this->member->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/tasks/{$task->id}", ['assignee_id' => $outsider->id]);

        $response->assertStatus(422)->assertJsonValidationErrors('assignee_id');
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'assignee_id' => $this->member->id]);
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
        $this->assertEquals(6, $response->json('statistics.total'));
        $this->assertEquals(2, $response->json('statistics.by_status.backlog'));
        $this->assertEquals(3, $response->json('statistics.by_status.in_progress'));
        $this->assertEquals(1, $response->json('statistics.by_status.done'));
    }

    public function test_my_tasks_rejects_invalid_filters_and_page_size(): void
    {
        $token = $this->member->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/my-tasks?status=invalid&priority=invalid&overdue=maybe&per_page=101');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status', 'priority', 'overdue', 'per_page']);
    }

    public function test_my_tasks_applies_filters_and_page_size(): void
    {
        Task::factory(3)->assignedTo($this->member)->withStatus('backlog')->create(['priority' => 'high']);
        Task::factory(2)->assignedTo($this->member)->withStatus('done')->create(['priority' => 'low']);
        $token = $this->member->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/my-tasks?status=backlog&priority=high&per_page=2');

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3);
        $this->assertCount(2, $response->json('data'));
        $this->assertSame(['backlog', 'backlog'], array_column($response->json('data'), 'status'));
    }
}
