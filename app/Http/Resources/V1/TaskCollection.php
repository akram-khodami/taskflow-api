<?php


namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class TaskCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => TaskResource::collection($this->collection),
            'meta' => [
                'total' => $this->total(),
                'per_page' => $this->perPage(),
                'current_page' => $this->currentPage(),
                'last_page' => $this->lastPage(),
            ],
            'filters' => [
                'status' => $request->status,
                'priority' => $request->priority,
                'assignee' => $request->assignee,
                'search' => $request->search,
            ],
        ];
    }
}
