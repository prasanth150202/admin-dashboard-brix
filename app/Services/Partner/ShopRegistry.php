<?php

namespace App\Services\Partner;

use App\Models\Shop;

/**
 * Finds or creates the local shop for a Partner API shop node, keyed on its
 * lowercased .myshopify.com domain so every app and feed lands on one row.
 */
class ShopRegistry
{
    /** @var array<string, Shop> */
    private array $seen = [];

    public function resolve(array $node): ?Shop
    {
        $domain = strtolower(trim((string) ($node['myshopifyDomain'] ?? '')));
        $domain = preg_replace('#^https?://#', '', rtrim($domain, '/'));

        if ($domain === '') {
            return null;
        }

        if (isset($this->seen[$domain])) {
            return $this->seen[$domain];
        }

        $shop = Shop::firstOrNew(['myshopify_domain' => $domain]);
        $shop->fill(array_filter([
            'partner_shop_id' => $node['id'] ?? null,
            'name' => $node['name'] ?? null,
        ]));
        $shop->save();

        return $this->seen[$domain] = $shop;
    }
}
