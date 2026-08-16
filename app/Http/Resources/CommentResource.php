<?php

// app/Http/Resources/CommentResource.php

namespace App\Http\Resources;

use App\Http\Resources\V1\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'preview' => $this->preview,
            'user' => new UserResource($this->whenLoaded('user')),
            'task_id' => $this->task_id,
            'parent_id' => $this->parent_id,
            'is_reply' => $this->isReply(),
            'replies' => CommentResource::collection($this->whenLoaded('replies')),
            'replies_count' => $this->whenCounted('replies'),
            'created_at' => $this->created_at?->toISOString(),
            'created_at_diff' => $this->created_at?->diffForHumans(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),

            // Additional meta for UI
            'can_edit' => $request->user() && $request->user()->can('update', $this->resource),
            'can_delete' => $request->user() && $request->user()->can('delete', $this->resource),
        ];
    }
}
