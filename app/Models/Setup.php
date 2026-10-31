<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Setup extends Model
{
    public const STATUSES = ['todo' => 'To do', 'in_progress' => 'In progress', 'waiting' => 'Waiting on merchant', 'done' => 'Done'];

    protected $fillable = ['shop_id', 'partner_app_id', 'status', 'assignee_id', 'due_on', 'notes', 'completed_at'];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'completed_at' => 'datetime',
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

    public function tasks(): HasMany
    {
        return $this->hasMany(SetupTask::class)->orderBy('position')->orderBy('id');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', '!=', 'done');
    }

    public function isOverdue(): bool
    {
        return $this->status !== 'done' && $this->due_on?->isBefore(today());
    }
}
