<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeatureRequest extends Model
{
    public const STATUSES = [
        'requested' => 'Requested',
        'planned' => 'Planned',
        'in_progress' => 'In development',
        'testing' => 'Testing',
        'done' => 'Released',
        'declined' => 'Declined',
    ];

    /** Statuses that close a request. */
    public const CLOSED = ['done', 'declined'];

    public const PRIORITIES = Ticket::PRIORITIES;

    protected $fillable = ['title', 'description', 'status', 'priority', 'partner_app_id', 'shop_id', 'requested_by', 'assignee_id', 'due_on', 'completed_at'];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(PartnerApp::class, 'partner_app_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(FeatureRequestUpdate::class)->orderBy('id');
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNotIn('status', self::CLOSED);
    }

    public function number(): string
    {
        return 'F-'.$this->id;
    }

    public function isOverdue(): bool
    {
        return ! in_array($this->status, self::CLOSED, true) && $this->due_on?->isBefore(today());
    }
}
