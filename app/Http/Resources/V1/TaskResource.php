<?php

namespace App\Http\Resources\V1;

use App\Http\Resources\CommentResource;
use App\Http\Resources\V1\UserResource;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'status_label' => Task::STATUSES[$this->status] ?? $this->status,
            'priority' => $this->priority,
            'priority_label' => Task::PRIORITIES[$this->priority] ?? $this->priority,
            'due_date' => $this->due_date?->toISOString(),
            'due_date_formatted' => $this->due_date?->format('Y-m-d'),
            'is_overdue' => $this->isOverdue(),
            'project' => new ProjectResource($this->whenLoaded('project')),
            'assignee' => new UserResource($this->whenLoaded('assignee')),
            'creator' => new UserResource($this->whenLoaded('creator')),
            'comments' => CommentResource::collection($this->whenLoaded('comments')), // اینجا رو عوض کن
            'comments_count' => $this->whenCounted('comments'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
