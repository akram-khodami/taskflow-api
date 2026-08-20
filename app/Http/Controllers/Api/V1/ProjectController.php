<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\ProjectIndexRequest;
use App\Http\Requests\V1\StoreProjectRequest;
use App\Http\Requests\V1\UpdateProjectRequest;
use App\Http\Resources\V1\ProjectResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{

    //todo:use servive class

    /**
     * Display a listing of projects
     */
    public function index(ProjectIndexRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();

        // Build filters array
        $filters = [
            'search' => $request->search,
            'owner_id' => $request->owner_id,
            'member_id' => $request->member_id,
            'trashed' => $request->trashed,
            'user' => $user,
        ];

        $query = Project::query()
            ->with(['owner', 'members'])
            ->withCount(['tasks', 'members'])
            ->filter($filters) // Apply filters from model
            ->sort(
                $request->input('sort_by', 'created_at'),
                $request->input('sort_order', 'desc')
            );

        // Paginate
        $perPage = $request->input('per_page', 15);
        $projects = $query->paginate($perPage);

        return ProjectResource::collection($projects);
    }
    /**
     * Store a newly created project
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        Gate::authorize('create', Project::class);

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

        Gate::authorize('update', $project);

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

            // Detach all members first
            $project->members()->detach();

            // Soft delete the project (tasks will be soft deleted via cascade)
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
