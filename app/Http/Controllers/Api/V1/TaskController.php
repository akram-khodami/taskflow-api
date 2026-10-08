<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\TaskIndexRequest;
use App\Http\Requests\V1\MyTaskIndexRequest;
use App\Http\Requests\V1\StoreTaskRequest;
use App\Http\Requests\V1\UpdateTaskRequest;
use App\Http\Requests\V1\UpdateTaskStatusRequest;
use App\Http\Resources\V1\TaskResource;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TaskController extends Controller
{
    /**
     * Display a listing of tasks for a project
     */
    public function index(TaskIndexRequest $request, Project $project)
    {
        Gate::authorize('viewAny', [Task::class, $project]);

        $query = Task::query()
            ->with(['assignee', 'creator', 'project'])
            ->where('project_id', $project->id)
            ->withCount(['comments'])
            ->status($request->status)
            ->priority($request->priority)
            ->assignee($request->assignee)
            ->search($request->search)
            ->dueDateRange($request->due_from, $request->due_to)
            ->overdue($request->boolean('overdue'))
            ->withTrashedIfRequested($request->boolean('trashed'))
            ->applySorting($request->input('sort_by', 'created_at'), $request->input('sort_order', 'desc'));

        $perPage = $request->input('per_page', 15);
        $tasks = $query->paginate($perPage);

        return TaskResource::collection($tasks);
    }

    /**
     * Store a newly created task
     */
    public function store(StoreTaskRequest $request, Project $project): JsonResponse
    {
        $validated = $request->validated();
        $task = Task::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'],
            'priority' => $validated['priority'],
            'due_date' => $validated['due_date'] ?? null,
            'project_id' => $project->id,
            'assignee_id' => $validated['assignee_id'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Task created successfully',
            'data' => new TaskResource($task->load(['assignee', 'creator', 'project'])),
        ], 201);
    }

    /**
     * Display the specified task
     */
    public function show(Request $request, Task $task): JsonResponse
    {
        Gate::authorize('view', $task);

        $task->load(['assignee', 'creator', 'project', 'comments.user']);

        return response()->json([
            'success' => true,
            'data' => new TaskResource($task),
        ], 200);
    }

    /**
     * Update the specified task
     */
    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        $task->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Task updated successfully',
            'data' => new TaskResource($task->load(['assignee', 'creator', 'project'])),
        ], 200);
    }

    /**
     * Update task status only
     */
    public function updateStatus(UpdateTaskStatusRequest $request, Task $task): JsonResponse
    {
        Gate::authorize('update', $task);

        $task->update([
            'status' => $request->validated('status'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Task status updated successfully',
            'data' => new TaskResource($task->load(['assignee', 'project'])),
        ], 200);
    }

    /**
     * Remove the specified task
     */
    public function destroy(Request $request, Task $task): JsonResponse
    {
        Gate::authorize('delete', $task);

        $task->delete();

        return response()->json([
            'success' => true,
            'message' => 'Task deleted successfully',
        ], 200);
    }

    /**
     * Restore a soft-deleted task
     */
    public function restore(Request $request, int $id): JsonResponse
    {
        $task = Task::withTrashed()->findOrFail($id);

        Gate::authorize('restore', $task);

        $task->restore();

        return response()->json([
            'success' => true,
            'message' => 'Task restored successfully',
            'data' => new TaskResource($task->load(['assignee', 'creator', 'project'])),
        ], 200);
    }

    /**
     * Permanently delete a task (force delete)
     */
    public function forceDelete(Request $request, int $id): JsonResponse
    {
        $task = Task::withTrashed()->findOrFail($id);

        Gate::authorize('forceDelete', $task);

        DB::transaction(function () use ($task): void {
            $task->comments()->delete();
            $task->forceDelete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Task permanently deleted',
        ], 200);
    }

    /**
     * Get all tasks assigned to the authenticated user
     * (My Tasks Dashboard)
     */
    public function myTasks(MyTaskIndexRequest $request)
    {
        $user = $request->user();
        $filters = $request->validated();

        $query = Task::query()
            ->with(['project', 'assignee', 'creator'])
            ->where('assignee_id', $user->id)
            ->withCount(['comments'])
            ->status($filters['status'] ?? null)
            ->priority($filters['priority'] ?? null)
            ->search($filters['search'] ?? null)
            ->overdue($request->boolean('overdue'))
            ->applySorting($filters['sort_by'] ?? 'created_at', $filters['sort_order'] ?? 'desc');

        $tasks = $query->paginate($filters['per_page'] ?? 15);

        // Get statistics grouped by status
        $statistics = Task::where('assignee_id', $user->id)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();


        return TaskResource::collection($tasks)->additional(
            [
                'statistics' => [
                    'total' => array_sum($statistics),
                    'by_status' => $statistics,
                    'overdue' => Task::where('assignee_id', $user->id)
                        ->whereDate('due_date', '<', today()->toDateString())
                        ->where('status', '!=', 'done')
                        ->count(),
                ]
            ]
        );
    }
}
