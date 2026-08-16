<?php

namespace App\Models;

use App\Policies\TaskPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UsePolicy(TaskPolicy::class)]
class Task extends Model
{
    use HasFactory, SoftDeletes;

    // ========== CONSTANTS ==========

    //todo:use enum and remove hear
    public const STATUSES = [
        'backlog' => 'Backlog',
        'in_progress' => 'In Progress',
        'in_review' => 'In Review',
        'done' => 'Done',
    ];

    //todo:use enum and remove hear
    public const PRIORITIES = [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'urgent' => 'Urgent',
    ];

    // ========== FILLABLE ==========

    protected $fillable = [
        'title',
        'description',
        'status',
        'priority',
        'due_date',
        'project_id',
        'assignee_id',
        'created_by',
    ];

    protected $casts = [
        'due_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ========== RELATIONSHIPS ==========

    /**
     * Get the project that owns the task
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user assigned to the task
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * Get the user who created the task
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the comments for the task
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Get the top-level comments for the task
     */
    public function topLevelComments(): HasMany
    {
        return $this->hasMany(Comment::class)->whereNull('parent_id');
    }


    // ========== SCOPES ==========

    /**
     * Scope a query to filter by status
     */
    public function scopeStatus($query, $status)
    {
        if ($status) {
            return $query->where('status', $status);
        }
        return $query;
    }

    /**
     * Scope a query to filter by priority
     */
    public function scopePriority($query, $priority)
    {
        if ($priority) {
            return $query->where('priority', $priority);
        }
        return $query;
    }

    /**
     * Scope a query to filter by assignee
     */
    public function scopeAssignee($query, $assigneeId)
    {
        if ($assigneeId) {
            return $query->where('assignee_id', $assigneeId);
        }
        return $query;
    }

    /**
     * Scope a query to search by title
     */
    public function scopeSearch($query, $search)
    {
        if ($search) {
            return $query->where('title', 'LIKE', "%{$search}%");
        }
        return $query;
    }

    /**
     * Scope a query to filter by project
     */
    public function scopeInProject($query, $projectId)
    {
        if ($projectId) {
            return $query->where('project_id', $projectId);
        }
        return $query;
    }

    /**
     * Scope a query to get tasks assigned to a user
     */
    public function scopeAssignedTo($query, $userId)
    {
        return $query->where('assignee_id', $userId);
    }

    // ========== HELPER METHODS ==========

    /**
     * Check if task is overdue
     */
    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date->isPast() && $this->status !== 'done';
    }
}
