<?php

namespace App\Models;

use App\Policies\ProjectPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UsePolicy(ProjectPolicy::class)]
class Project extends Model
{
    /** @use HasFactory<\Database\Factories\ProjectFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'owner_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ========== RELATIONSHIPS ==========

    /**
     * Get the owner of the project
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the members of the project
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_user')
            ->withTimestamps();
    }

    /**
     * Get the tasks of the project
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    // ========== HELPER METHODS ==========

    /**
     * Check if a user is a member of the project
     */
    public function isMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Check if a user is the owner of the project
     */
    public function isOwner(User $user): bool
    {
        return $this->owner_id === $user->id;
    }

    /**
     * Check if a user has access to the project (is member or owner)
     */
    public function hasAccess(User $user): bool
    {
        return $this->isOwner($user) || $this->isMember($user);
    }

    /**
     * Get all users who have access to the project
     */
    public function accessibleUsers(): \Illuminate\Support\Collection
    {
        return collect([$this->owner])->merge($this->members)->unique('id');
    }
}
