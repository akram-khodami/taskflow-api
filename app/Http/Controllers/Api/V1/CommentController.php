<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\CommentIndexRequest;
use App\Http\Requests\V1\StoreCommentRequest;
use App\Http\Requests\V1\UpdateCommentRequest;
use App\Http\Resources\CommentCollection;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    /**
     * Display a listing of comments for a task
     */
    public function index(CommentIndexRequest $request, Task $task): CommentCollection
    {
        // Check authorization via Policy
        Gate::authorize('viewAny', [Comment::class, $task]);

        $query = Comment::query()
            ->with(['user', 'replies.user'])
            ->where('task_id', $task->id)
            ->topLevel() // Only get top-level comments, replies loaded separately
            ->withCount(['replies']);

        // Sort
        $sortField = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $allowedSortFields = ['created_at', 'updated_at'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortOrder);
        }

        // Paginate
        $perPage = $request->get('per_page', 20);
        $comments = $query->paginate($perPage);

        return new CommentCollection($comments);
    }

    /**
     * Store a newly created comment
     */
    public function store(StoreCommentRequest $request, Task $task): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validated = $request->validated();

            // If replying to a parent comment, verify it exists
            if (isset($validated['parent_id'])) {
                $parent = Comment::find($validated['parent_id']);
                // Parent must belong to the same task
                if ($parent && $parent->task_id !== $task->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Parent comment does not belong to this task',
                    ], 422);
                }
            }

            $comment = Comment::create([
                'body' => $validated['body'],
                'task_id' => $task->id,
                'user_id' => $request->user()->id,
                'parent_id' => $validated['parent_id'] ?? null,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Comment added successfully',
                'data' => new CommentResource($comment->load(['user'])),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to add comment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified comment
     */
    public function show(Request $request, Comment $comment): JsonResponse
    {
        // Check authorization via Policy
        Gate::authorize('view', $comment);

        $comment->load(['user', 'replies.user', 'parent']);

        return response()->json([
            'success' => true,
            'data' => new CommentResource($comment),
        ], 200);
    }

    /**
     * Update the specified comment
     */
    public function update(UpdateCommentRequest $request, Comment $comment): JsonResponse
    {
        try {
            $comment->update([
                'body' => $request->body,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Comment updated successfully',
                'data' => new CommentResource($comment->load(['user'])),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update comment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified comment
     */
    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        // Check authorization via Policy
        Gate::authorize('delete', $comment);

        try {
            DB::beginTransaction();

            // If comment has replies, we can either:
            // Option 1: Soft delete comment (replies remain, but show "deleted")
            // Option 2: Delete all replies too

            // For now, we'll soft delete and also soft delete all replies
            $comment->replies()->delete();
            $comment->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Comment and its replies deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete comment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Restore a soft-deleted comment
     */
    public function restore(Request $request, int $id): JsonResponse
    {
        $comment = Comment::withTrashed()->findOrFail($id);

        // Check authorization via Policy
        Gate::authorize('restore', $comment);

        try {
            DB::beginTransaction();

            $comment->restore();
            // Also restore replies
            $comment->replies()->withTrashed()->restore();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Comment restored successfully',
                'data' => new CommentResource($comment->load(['user'])),
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore comment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Permanently delete a comment (force delete)
     */
    public function forceDelete(Request $request, int $id): JsonResponse
    {
        $comment = Comment::withTrashed()->findOrFail($id);

        // Check authorization via Policy
        Gate::authorize('forceDelete', $comment);

        try {
            DB::beginTransaction();

            // Delete replies first
            $comment->replies()->withTrashed()->forceDelete();
            $comment->forceDelete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Comment permanently deleted',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to permanently delete comment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get replies for a specific comment
     */
    public function replies(Request $request, Comment $comment): JsonResponse
    {
        // Check if user can view the comment
        Gate::authorize('view', $comment);

        $replies = $comment->replies()
            ->with(['user'])
            ->orderBy('created_at', 'asc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => [
                'comment' => new CommentResource($comment),
                'replies' => new CommentCollection($replies),
            ],
        ], 200);
    }
}
