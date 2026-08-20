<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    /**
     * Display a listing of users
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = User::query();

        // ========== FILTERS ==========

        // Search by name or email
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        // Filter by role
        if ($request->has('role') && !empty($request->role)) {
            $query->where('role', $request->role);
        }

        // Exclude current user (for assignee selection)
        if ($request->has('exclude_self') && $request->exclude_self === 'true') {
            $query->where('id', '!=', $request->user()->id);
        }

        // Get only users who are admins or managers (for project owner selection)
        if ($request->has('only_managers') && $request->only_managers === 'true') {
            $query->whereIn('role', ['admin', 'manager']);
        }

        // Get only users who are not admins (for member selection)
        if ($request->has('exclude_admins') && $request->exclude_admins === 'true') {
            $query->where('role', '!=', 'admin');
        }

        // Get users who are members of a specific project
        if ($request->has('project_id') && $request->project_id) {
            $query->whereHas('projects', function ($q) use ($request) {
                $q->where('project_id', $request->project_id);
            });
        }

        // Get users who are NOT members of a specific project
        if ($request->has('not_in_project') && $request->not_in_project) {
            $query->whereDoesntHave('projects', function ($q) use ($request) {
                $q->where('project_id', $request->not_in_project);
            });
        }

        // ========== SORTING ==========

        $sortField = $request->input('sort_by', 'name');
        $sortOrder = $request->input('sort_order', 'asc');

        $allowedSortFields = ['id', 'name', 'email', 'role', 'created_at'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortOrder);
        }

        // ========== PAGINATION ==========

        $perPage = $request->input('per_page', 15);
        $users = $query->paginate($perPage);

        return UserResource::collection($users);
    }

    /**
     * Display the specified user
     */
    public function show(Request $request, User $user): JsonResponse
    {
        //todo:user policy for viewing user details
        if (!$request->user()->isAdmin() && $request->user()->id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view this user',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => new UserResource($user),
        ], 200);
    }
}
