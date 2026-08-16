<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TaskPolicy
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
     * Determine if user can view tasks
     */
    public function viewAny(User $user, Project $project): bool
    {
        // Admin can view all
        if ($this->isAdmin($user)) {
            return true;
        }

        // User must be member or owner of the project
        return $project->hasAccess($user);
    }

    /**
     * Determine if user can view a specific task
     */
    public function view(User $user, Task $task): bool
    {
        // Admin can view all
        if ($this->isAdmin($user)) {
            return true;
        }

        // User must have access to the project
        return $task->project->hasAccess($user);
    }

    /**
     * Determine if user can create tasks
     */
    public function create(User $user, Project $project): bool
    {
        // Admin and Manager can create tasks
        if ($user->hasAnyRole(['admin', 'manager'])) {
            return true;
        }

        // Member can only create tasks if they are member of the project
        return $project->isMember($user);
    }

    /**
     * Determine if user can update a task
     */
    public function update(User $user, Task $task): bool
    {
        // Admin can update all
        if ($this->isAdmin($user)) {
            return true;
        }

        $project = $task->project;

        // Manager can update if they have access to the project
        if ($user->isManager() && $project->hasAccess($user)) {
            return true;
        }

        // Member can update if they are the assignee
        if ($user->isMember() && $task->assignee_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine if user can update task status
     */
    public function updateStatus(User $user, Task $task): bool
    {
        // Admin can update all
        if ($this->isAdmin($user)) {
            return true;
        }

        $project = $task->project;

        // Manager can update if they have access to the project
        if ($user->isManager() && $project->hasAccess($user)) {
            return true;
        }

        // Member can update if they are the assignee
        if ($user->isMember() && $task->assignee_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine if user can delete a task
     */
    public function delete(User $user, Task $task): bool
    {
        // Admin can delete all
        if ($this->isAdmin($user)) {
            return true;
        }

        $project = $task->project;

        // Manager can delete if they have access to the project
        if ($user->isManager() && $project->hasAccess($user)) {
            return true;
        }

        // Member can delete if they are the assignee
        // if ($user->isMember() && $task->assignee_id === $user->id) {
        //     return true;
        // }

        return false;
    }

    /**
     * Determine if user can restore a task
     */
    public function restore(User $user, Task $task): bool
    {
        // Same as delete
        return $this->delete($user, $task);
    }

    /**
     * Determine if user can force delete a task
     */
    public function forceDelete(User $user, Task $task): bool
    {
        // Only admin can force delete
        return $this->isAdmin($user);
    }
}
