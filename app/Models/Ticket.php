<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    public const STATUSES = ['open' => 'Open', 'pending' => 'Waiting on merchant', 'resolved' => 'Resolved'];

    public const PRIORITIES = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];

    public const SOURCES = ['manual' => 'Team', 'form' => 'Support form', 'email' => 'Email', 'chatbot' => 'Chatbot'];

    protected $fillable = [
        'subject', 'status', 'priority', 'source', 'external_ref', 'shop_id', 'shop_domain',
        'partner_app_id', 'assignee_id', 'requester_name', 'requester_email', 'last_activity_at', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'last_activity_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(PartnerApp::class, 'partner_app_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->orderBy('id');
    }

    /** Open or waiting on the merchant — anything not resolved yet. */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', '!=', 'resolved');
    }

    public function number(): string
    {
        return "#{$this->id}";
    }

    /** The tag replies must keep in their subject to thread back here. */
    public function mailTag(): string
    {
        return "[#{$this->id}]";
    }

    public function requesterLabel(): string
    {
        return $this->requester_name ?: ($this->requester_email ?: 'Unknown');
    }
}
