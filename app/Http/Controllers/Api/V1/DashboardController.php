<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        return response()->json([
            'projects_count' => Project::count(),
            'tasks_count' => Task::count(),
            'users_count' => User::count(),
        ]);
    }
}
