<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Setup */
class SetupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'store' => StoreResource::make($this->whenLoaded('shop')),
            'app' => AppResource::make($this->whenLoaded('app')),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? UserResource::make($this->assignee) : null),
            'due_on' => $this->due_on?->toDateString(),
            'overdue' => $this->isOverdue(),
            'notes' => $this->notes,
            'progress' => $this->whenLoaded('tasks', fn () => [
                'done' => $this->tasks->whereNotNull('done_at')->count(),
                'total' => $this->tasks->count(),
            ]),
            'tasks' => SetupTaskResource::collection($this->whenLoaded('tasks')),
            'url' => route('setups.show', $this->resource),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
