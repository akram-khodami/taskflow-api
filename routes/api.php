<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CommentController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\TaskController;

Route::prefix('v1')->group(function () {
    // ========== Public Routes ==========
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    // ========== Protected Routes ==========
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);


        // ========== PROJECTS ==========
        Route::get('/projects', [ProjectController::class, 'index']);
        Route::post('/projects', [ProjectController::class, 'store']);
        Route::get('/projects/{project}', [ProjectController::class, 'show']);
        Route::put('/projects/{project}', [ProjectController::class, 'update']);
        Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);

        // Soft Delete - Restore
        Route::post('/projects/{id}/restore', [ProjectController::class, 'restore']);
        Route::delete('/projects/{id}/force', [ProjectController::class, 'forceDelete']);

        // ========== TASK ROUTES (Nested in Project) ==========
        Route::prefix('projects/{project}')->group(function () {
            Route::get('/tasks', [TaskController::class, 'index']);
            Route::post('/tasks', [TaskController::class, 'store']);
        });

        // ========== TASK ROUTES (Direct) ==========
        Route::prefix('tasks')->group(function () {
            Route::get('/{task}', [TaskController::class, 'show']);
            Route::put('/{task}', [TaskController::class, 'update']);
            Route::patch('/{task}/status', [TaskController::class, 'updateStatus']);
            Route::delete('/{task}', [TaskController::class, 'destroy']);

            // Soft Delete - Restore & Force Delete
            Route::post('/{id}/restore', [TaskController::class, 'restore']);
            Route::delete('/{id}/force', [TaskController::class, 'forceDelete']);
        });

        // ========== MY TASKS ==========
        Route::get('/my-tasks', [TaskController::class, 'myTasks']);


        // ========== COMMENT ROUTES (Nested in Task) ==========
        Route::prefix('tasks/{task}')->group(function () {
            Route::get('/comments', [CommentController::class, 'index']);
            Route::post('/comments', [CommentController::class, 'store']);
        });
         // ========== COMMENT ROUTES (Direct) ==========
    Route::prefix('comments')->group(function () {
        Route::get('/{comment}', [CommentController::class, 'show']);
        Route::put('/{comment}', [CommentController::class, 'update']);
        Route::delete('/{comment}', [CommentController::class, 'destroy']);

        // Get replies for a comment
        Route::get('/{comment}/replies', [CommentController::class, 'replies']);

        // Soft Delete - Restore & Force Delete
        Route::post('/{id}/restore', [CommentController::class, 'restore']);
        Route::delete('/{id}/force', [CommentController::class, 'forceDelete']);
    });
    });
});
