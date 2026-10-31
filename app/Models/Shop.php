<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shop extends Model
{
    public const KIND_CLIENT = 'client';

    public const KIND_TEST = 'test';

    public const KIND_UNKNOWN = 'unknown';

    protected $fillable = [
        'myshopify_domain', 'partner_shop_id', 'name', 'kind', 'kind_override',
        'is_paid', 'lifetime_net', 'first_seen_at', 'last_charge_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'lifetime_net' => 'decimal:2',
            'first_seen_at' => 'datetime',
            'last_charge_at' => 'datetime',
        ];
    }

    public function installs(): HasMany
    {
        return $this->hasMany(AppInstall::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AppEvent::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function setups(): HasMany
    {
        return $this->hasMany(Setup::class);
    }

    public function displayName(): string
    {
        return $this->name ?: $this->myshopify_domain;
    }
}
