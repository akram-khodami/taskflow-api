<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class ProjectPolicy
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
     * Determine if user can view any projects
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view projects
        return true;
    }

    /**
     * Determine if user can view a specific project
     */
    public function view(User $user, Project $project): bool
    {
        // Admin can view all
        if ($this->isAdmin($user)) {
            return true;
        }

        // User must be member or owner of the project
        return $project->hasAccess($user);
    }

    /**
     * Determine if user can create projects
     */
    public function create(User $user): bool
    {
        // Admin and Manager can create projects
        return $user->hasAnyRole(['admin', 'manager']);
    }

    /**
     * Determine if user can update a project
     */
    public function update(User $user, Project $project): bool
    {
        // Admin can update all
        if ($this->isAdmin($user)) {
            return true;
        }

        // Manager can update if they are the owner
        return $user->hasRole('manager') && $project->isOwner($user);
    }

    /**
     * Determine if user can delete a project
     */
    public function delete(User $user, Project $project): bool
    {
        // Admin can delete all
        if ($this->isAdmin($user)) {
            return true;
        }

        // Manager can delete if they are the owner
        return $user->hasRole('manager') && $project->isOwner($user);
    }

    /**
     * Determine if user can restore a project
     */
    public function restore(User $user, Project $project): bool
    {
        // Admin and Manager (owner) can restore
        if ($this->isAdmin($user)) {
            return true;
        }

        return $user->hasRole('manager') && $project->isOwner($user);
    }

    /**
     * Determine if user can permanently delete a project
     */
    public function forceDelete(User $user, Project $project): bool
    {
        // Only admin can force delete
        return $this->isAdmin($user);
    }
}
