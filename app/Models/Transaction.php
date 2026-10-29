<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'partner_transaction_id', 'partner_app_id', 'shop_id', 'type', 'occurred_at',
        'gross_amount', 'shopify_fee', 'net_amount', 'currency', 'charge_id',
        'billing_interval', 'payout_period_start', 'payout_period_end',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'gross_amount' => 'decimal:2',
            'shopify_fee' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'payout_period_start' => 'date',
            'payout_period_end' => 'date',
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

    /** "AppSubscriptionSale" → "Subscription sale". */
    public function label(): string
    {
        return ucfirst(strtolower(trim(preg_replace('/([A-Z])/', ' $1', substr($this->type, 3)))));
    }
}
