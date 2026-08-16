<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\StoreProjectRequest;
use App\Http\Requests\V1\UpdateProjectRequest;
use App\Http\Resources\ProjectCollection;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{

//todo:use servive class
    /**
     * Display a listing of projects
     */
    public function index(Request $request): ProjectCollection
    {
        $query = Project::query()
            ->with(['owner', 'members'])
            ->withCount(['tasks', 'members']);

        //todo:filter move to model use when
        // Filter by search term (name or description)
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // Filter by owner
        if ($request->has('owner_id') && $request->owner_id) {
            $query->where('owner_id', $request->owner_id);
        }

        // Filter by member
        if ($request->has('member_id') && $request->member_id) {
            $query->whereHas('members', function ($q) use ($request) {
                $q->where('user_id', $request->member_id);
            });
        }

        // Show only projects the user has access to (unless admin)
        $user = $request->user();
        if (!$user->isAdmin()) {
            $query->where(function ($q) use ($user) {
                $q->where('owner_id', $user->id)
                    ->orWhereHas('members', function ($subQuery) use ($user) {
                        $subQuery->where('user_id', $user->id);
                    });
            });
        }

        // Include trashed projects if requested
        if ($request->has('trashed') && $request->trashed === 'true') {
            $query->withTrashed();
        }

        // Sort
        $sortField = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortField, $sortOrder);

        // Paginate
        $perPage = $request->input('per_page', 15);
        $projects = $query->paginate($perPage);

        return new ProjectCollection($projects);
    }

    /**
     * Store a newly created project
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validated = $request->validated();

            $project = Project::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'owner_id' => $request->user()->id,
            ]);

            // Attach members
            if (!empty($validated['members'])) {
                // Ensure owner is not duplicated in members
                $members = array_diff($validated['members'], [$request->user()->id]);
                if (!empty($members)) {
                    $project->members()->attach($members);
                }
            }

            // Add owner as member too
            $project->members()->syncWithoutDetaching([$request->user()->id]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Project created successfully',
                'data' => new ProjectResource($project->load(['owner', 'members'])),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create project',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified project
     */
    public function show(Request $request, Project $project): JsonResponse
    {
        // Check authorization via Policy
        Gate::authorize('view', $project);

        $project->load(['owner', 'members', 'tasks']);

        return response()->json([
            'success' => true,
            'data' => new ProjectResource($project),
        ], 200);
    }

    /**
     * Update the specified project
     */
    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validated = $request->validated();

            // Update project details
            $project->update([
                'name' => $validated['name'] ?? $project->name,
                'description' => $validated['description'] ?? $project->description,
            ]);

            // Update members if provided
            if (isset($validated['members'])) {
                // Ensure owner is always a member
                $members = array_diff($validated['members'], [$project->owner_id]);
                $members[] = $project->owner_id;

                // Sync members (with owner always included)
                $project->members()->sync($members);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Project updated successfully',
                'data' => new ProjectResource($project->load(['owner', 'members'])),
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update project',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified project
     */
    public function destroy(Request $request, Project $project): JsonResponse
    {
        // Check authorization via Policy
        Gate::authorize('delete', $project);

        try {
            DB::beginTransaction();

            // Delete associated tasks and comments?
            // We'll use soft delete so they remain
            $project->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Project deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete project',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Restore a soft-deleted project
     */
    public function restore(Request $request, int $id): JsonResponse
    {
        $project = Project::withTrashed()->findOrFail($id);

        // Check authorization via Policy
        Gate::authorize('restore', $project);

        try {
            $project->restore();

            return response()->json([
                'success' => true,
                'message' => 'Project restored successfully',
                'data' => new ProjectResource($project->load(['owner', 'members'])),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore project',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Permanently delete a project (force delete)
     */
    public function forceDelete(Request $request, int $id): JsonResponse
    {
        $project = Project::withTrashed()->findOrFail($id);

        // Check authorization via Policy
        Gate::authorize('forceDelete', $project);

        try {
            DB::beginTransaction();

            // Delete related data
            $project->tasks()->forceDelete();
            $project->members()->detach();
            $project->forceDelete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Project permanently deleted',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to permanently delete project',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
