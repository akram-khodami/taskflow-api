<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;
    private User $member;
    private User $member2;
    private Project $project;
    private Task $task;

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

        // Create a task in the project
        $this->task = Task::factory()
            ->forProject($this->project)
            ->assignedTo($this->member)
            ->create();
    }

    // ============================================
    // CREATE COMMENT
    // ============================================

    public function test_project_member_can_add_comment_to_task(): void
    {
        $token = $this->member->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/tasks/{$this->task->id}/comments", [
                'body' => 'This is a great task!',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Comment added successfully',
            ]);

        $this->assertDatabaseHas('comments', [
            'body' => 'This is a great task!',
            'task_id' => $this->task->id,
            'user_id' => $this->member->id,
        ]);
    }

    public function test_manager_can_add_comment_to_any_task(): void
    {
        $token = $this->manager->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/tasks/{$this->task->id}/comments", [
                'body' => 'Manager comment',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('comments', [
            'body' => 'Manager comment',
            'user_id' => $this->manager->id,
        ]);
    }

    public function test_user_not_in_project_cannot_add_comment(): void
    {
        $outsider = User::factory()->member()->create();
        $token = $outsider->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/tasks/{$this->task->id}/comments", [
                'body' => 'Unauthorized comment',
            ]);

        $response->assertStatus(403);
    }

    public function test_can_add_reply_to_comment(): void
    {
        $parentComment = Comment::factory()
            ->forTask($this->task)
            ->byUser($this->member)
            ->create();

        $token = $this->member2->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/tasks/{$this->task->id}/comments", [
                'body' => 'This is a reply',
                'parent_id' => $parentComment->id,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('comments', [
            'body' => 'This is a reply',
            'parent_id' => $parentComment->id,
            'task_id' => $this->task->id,
        ]);
    }

    // public function test_cannot_reply_to_comment_from_different_task(): void
    // {
    //     // Create another task
    //     $otherTask = Task::factory()->forProject($this->project)->create();
    //     $otherComment = Comment::factory()->forTask($otherTask)->create();

    //     $token = $this->member->createToken('auth_token')->plainTextToken;

    //     $response = $this->withHeader('Authorization', "Bearer {$token}")
    //         ->postJson("/api/v1/tasks/{$this->task->id}/comments", [
    //             'body' => 'Reply to different task',
    //             'parent_id' => $otherComment->id,
    //         ]);

    //     $response->assertStatus(422);
    // }

    // ============================================
    // VIEW COMMENTS
    // ============================================

    public function test_user_can_view_comments_for_task(): void
    {
        Comment::factory(5)->forTask($this->task)->create();

        $token = $this->member->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/tasks/{$this->task->id}/comments");

        $response->assertStatus(200);
        $this->assertCount(5, $response->json('data'));
    }

    public function test_comment_index_rejects_invalid_filters(): void
    {
        $token = $this->member->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/tasks/{$this->task->id}/comments?sort_by=invalid&sort_order=invalid&per_page=0");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sort_by', 'sort_order', 'per_page']);
    }

    public function test_comments_are_shown_with_replies_count(): void
    {
        $parent = Comment::factory()->forTask($this->task)->byUser($this->member)->create();
        Comment::factory(3)->replyTo($parent)->create();

        $token = $this->member->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/tasks/{$this->task->id}/comments");

        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('data.0.replies_count'));
    }

    public function test_can_view_specific_comment(): void
    {
        $comment = Comment::factory()->forTask($this->task)->byUser($this->member)->create();

        $token = $this->member->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/comments/{$comment->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $comment->id,
                    'body' => $comment->body,
                ],
            ]);
    }

    public function test_can_view_replies_for_comment(): void
    {
        $parent = Comment::factory()->forTask($this->task)->byUser($this->member)->create();
        $replies = Comment::factory(3)->replyTo($parent)->create();

        $token = $this->member->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/comments/{$parent->id}/replies");

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data.replies.data'));
    }

    // ============================================
    // UPDATE COMMENT
    // ============================================

    public function test_user_can_update_their_own_comment(): void
    {
        $comment = Comment::factory()
            ->forTask($this->task)
            ->byUser($this->member)
            ->create();

        $token = $this->member->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/comments/{$comment->id}", [
                'body' => 'Updated comment body',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'body' => 'Updated comment body',
        ]);
    }

    public function test_user_cannot_update_other_users_comment(): void
    {
        $comment = Comment::factory()
            ->forTask($this->task)
            ->byUser($this->member2)
            ->create();

        $token = $this->member->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/comments/{$comment->id}", [
                'body' => 'Hacked content',
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_update_any_comment(): void
    {
        $comment = Comment::factory()
            ->forTask($this->task)
            ->byUser($this->member)
            ->create();

        $token = $this->admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/comments/{$comment->id}", [
                'body' => 'Admin updated this',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'body' => 'Admin updated this',
        ]);
    }

    // ============================================
    // DELETE COMMENT
    // ============================================

    public function test_user_can_delete_their_own_comment(): void
    {
        $comment = Comment::factory()
            ->forTask($this->task)
            ->byUser($this->member)
            ->create();

        $token = $this->member->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/comments/{$comment->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted($comment);
    }

    public function test_deleting_comment_also_deletes_replies(): void
    {
        $parent = Comment::factory()->forTask($this->task)->byUser($this->member)->create();
        $reply1 = Comment::factory()->replyTo($parent)->create();
        $reply2 = Comment::factory()->replyTo($parent)->create();

        $token = $this->member->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/comments/{$parent->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted($parent);
        $this->assertSoftDeleted($reply1);
        $this->assertSoftDeleted($reply2);
    }

    public function test_user_cannot_delete_other_users_comment(): void
    {
        $comment = Comment::factory()
            ->forTask($this->task)
            ->byUser($this->member2)
            ->create();

        $token = $this->member->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/comments/{$comment->id}");

        $response->assertStatus(403);
    }

    public function test_manager_can_delete_their_own_comment(): void
    {
        $comment = Comment::factory()
            ->forTask($this->task)
            ->byUser($this->manager)
            ->create();

        $token = $this->manager->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/comments/{$comment->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted($comment);
    }

    public function test_admin_can_delete_any_comment(): void
    {
        $comment = Comment::factory()
            ->forTask($this->task)
            ->byUser($this->member)
            ->create();

        $token = $this->admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/comments/{$comment->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted($comment);
    }

    // ============================================
    // RESTORE COMMENT
    // ============================================

    public function test_user_can_restore_their_deleted_comment(): void
    {
        $comment = Comment::factory()
            ->forTask($this->task)
            ->byUser($this->member)
            ->create();
        $comment->delete();

        $token = $this->member->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/comments/{$comment->id}/restore");

        $response->assertStatus(200);
        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'deleted_at' => null,
        ]);
    }

    // ============================================
    // FORCE DELETE
    // ============================================

    public function test_admin_can_force_delete_comment(): void
    {
        $comment = Comment::factory()
            ->forTask($this->task)
            ->byUser($this->member)
            ->create();
        $comment->delete();

        $token = $this->admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/comments/{$comment->id}/force");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('comments', [
            'id' => $comment->id,
        ]);
    }

    public function test_member_cannot_force_delete_comment(): void
    {
        $comment = Comment::factory()
            ->forTask($this->task)
            ->byUser($this->member)
            ->create();
        $comment->delete();

        $token = $this->member->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/comments/{$comment->id}/force");

        $response->assertStatus(403);
    }
}
