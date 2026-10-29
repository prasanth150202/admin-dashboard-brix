<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppEvent extends Model
{
    public const INSTALLED = 'RELATIONSHIP_INSTALLED';

    public const UNINSTALLED = 'RELATIONSHIP_UNINSTALLED';

    public const DEACTIVATED = 'RELATIONSHIP_DEACTIVATED';

    public const REACTIVATED = 'RELATIONSHIP_REACTIVATED';

    protected $fillable = [
        'partner_app_id', 'shop_id', 'type', 'occurred_at', 'charge_id', 'charge_name',
        'charge_amount', 'charge_currency', 'charge_test', 'billing_on', 'reason',
        'description', 'dedupe_key', 'payload',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'billing_on' => 'datetime',
            'charge_amount' => 'decimal:2',
            'charge_test' => 'boolean',
            'payload' => 'array',
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

    /** "SUBSCRIPTION_CHARGE_ACTIVATED" → "Subscription charge activated". */
    public function label(): string
    {
        return ucfirst(strtolower(str_replace(['RELATIONSHIP_', '_'], ['', ' '], $this->type)));
    }
}
