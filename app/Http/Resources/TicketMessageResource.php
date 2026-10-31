<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\TicketMessage */
class TicketMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'from' => $this->fromTeam() ? 'team' : 'merchant',
            'author' => ['name' => $this->author_name, 'email' => $this->author_email],
            'body' => $this->body,
            'internal' => $this->is_internal,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
