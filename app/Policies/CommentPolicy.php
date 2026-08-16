<?php
// app/Policies/CommentPolicy.php

namespace App\Policies;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CommentPolicy
{
    use HandlesAuthorization;

    /**
     * Admin can do everything
     */
    private function isAdmin(User $user): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine if user can view comments for a task
     */
    public function viewAny(User $user, Task $task): bool
    {
        // Admin can view all
        if ($this->isAdmin($user)) {
            return true;
        }

        // User must be member or owner of the project
        return $task->project->hasAccess($user);
    }

    /**
     * Determine if user can view a specific comment
     */
    public function view(User $user, Comment $comment): bool
    {
        // Admin can view all
        if ($this->isAdmin($user)) {
            return true;
        }

        // User must have access to the task's project
        return $comment->task->project->hasAccess($user);
    }

    /**
     * Determine if user can create a comment
     */
    public function create(User $user, Task $task): bool
    {
        // Admin can create comments on any task
        if ($this->isAdmin($user)) {
            return true;
        }

        // User must be a member or owner of the project
        return $task->project->hasAccess($user);
    }

    /**
     * Determine if user can update a comment
     */
    public function update(User $user, Comment $comment): bool
    {
        // Admin can update any comment
        if ($this->isAdmin($user)) {
            return true;
        }

        // Manager can update their own comments
        if ($user->isManager() && $comment->user_id === $user->id) {
            return true;
        }

        // User can update their own comments
        return $comment->user_id === $user->id;
    }

    /**
     * Determine if user can delete a comment
     */
    public function delete(User $user, Comment $comment): bool
    {
        // Admin can delete any comment
        if ($this->isAdmin($user)) {
            return true;
        }

        // Manager can delete their own comments
        if ($user->isManager() && $comment->user_id === $user->id) {
            return true;
        }

        // User can delete their own comments
        return $comment->user_id === $user->id;
    }

    /**
     * Determine if user can restore a comment
     */
    public function restore(User $user, Comment $comment): bool
    {
        // Same as delete
        return $this->delete($user, $comment);
    }

    /**
     * Determine if user can force delete a comment
     */
    public function forceDelete(User $user, Comment $comment): bool
    {
        // Only admin can force delete
        return $this->isAdmin($user);
    }
}
