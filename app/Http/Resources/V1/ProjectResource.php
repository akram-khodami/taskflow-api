<?php

namespace App\Http\Resources\V1;

use App\Http\Resources\V1\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'owner' => new UserResource($this->whenLoaded('owner')),
            'tasks' => TaskResource::collection($this->whenLoaded('tasks')),
            'members' => UserResource::collection($this->whenLoaded('members')),
            'tasks_count' => $this->whenCounted('tasks'),
            'members_count' => $this->whenCounted('members'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
