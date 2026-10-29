<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppInstall extends Model
{
    public const STATUS_INSTALLED = 'installed';

    public const STATUS_UNINSTALLED = 'uninstalled';

    public const STATUS_DEACTIVATED = 'deactivated';

    protected $fillable = [
        'partner_app_id', 'shop_id', 'status', 'first_installed_at', 'last_installed_at',
        'uninstalled_at', 'install_count', 'uninstall_reason', 'plan_name', 'plan_amount',
        'plan_currency', 'plan_test', 'plan_status', 'plan_billing_on', 'lifetime_net',
    ];

    protected function casts(): array
    {
        return [
            'first_installed_at' => 'datetime',
            'last_installed_at' => 'datetime',
            'uninstalled_at' => 'datetime',
            'plan_billing_on' => 'datetime',
            'plan_amount' => 'decimal:2',
            'plan_test' => 'boolean',
            'lifetime_net' => 'decimal:2',
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

    /** Installed right now and on an active, real (non-test), non-zero plan. */
    public function scopePayingNow(Builder $query): void
    {
        $query->where('status', self::STATUS_INSTALLED)
            ->where('plan_status', 'active')
            ->where('plan_test', false)
            ->where('plan_amount', '>', 0);
    }
}
