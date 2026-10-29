<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnerApp extends Model
{
    protected $fillable = ['partner_app_id', 'name', 'api_key', 'events_synced_until', 'transactions_synced_until'];

    protected function casts(): array
    {
        return [
            'events_synced_until' => 'datetime',
            'transactions_synced_until' => 'datetime',
        ];
    }

    public function gid(): string
    {
        return "gid://partners/App/{$this->partner_app_id}";
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
}
