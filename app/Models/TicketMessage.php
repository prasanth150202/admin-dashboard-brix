<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketMessage extends Model
{
    protected $fillable = ['ticket_id', 'user_id', 'author_name', 'author_email', 'body', 'is_internal', 'email_message_id'];

    protected function casts(): array
    {
        return ['is_internal' => 'boolean'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fromTeam(): bool
    {
        return $this->user_id !== null;
    }

    public function authorLabel(): string
    {
        return $this->user?->name ?? $this->author_name ?? $this->author_email ?? 'Merchant';
    }
}
