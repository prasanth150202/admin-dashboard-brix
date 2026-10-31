<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Ticket */
class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number(),
            'subject' => $this->subject,
            'status' => $this->status,
            'priority' => $this->priority,
            'source' => $this->source,
            'conversation_id' => $this->external_ref,
            'requester' => ['name' => $this->requester_name, 'email' => $this->requester_email],
            'shop_domain' => $this->shop_domain,
            'store' => $this->whenLoaded('shop', fn () => $this->shop ? StoreResource::make($this->shop) : null),
            'app' => $this->whenLoaded('app', fn () => $this->app ? AppResource::make($this->app) : null),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? UserResource::make($this->assignee) : null),
            'messages' => TicketMessageResource::collection($this->whenLoaded('messages')),
            'url' => route('tickets.show', $this->resource),
            'last_activity_at' => $this->last_activity_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
