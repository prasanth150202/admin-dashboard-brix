<?php

namespace App\Services\Partner;

use App\Models\PartnerApp;
use App\Models\Transaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Pulls app revenue transactions for one app (needs "View financials").
 * netAmount is what the partner actually earns, after Shopify's fee;
 * credits and adjustments arrive as their own rows with negative amounts.
 * Test charges on development stores never produce transactions.
 */
class TransactionSync
{
    private const PAGE_SIZE = 100;

    private const MAX_PAGES = 2000;

    private const TYPES = [
        'APP_SUBSCRIPTION_SALE',
        'APP_ONE_TIME_SALE',
        'APP_USAGE_SALE',
        'APP_SALE_ADJUSTMENT',
        'APP_SALE_CREDIT',
    ];

    private const QUERY = <<<'GQL'
        query($appId: ID!, $createdAtMin: DateTime!, $types: [TransactionType!], $first: Int!, $after: String) {
            transactions(appId: $appId, createdAtMin: $createdAtMin, types: $types, first: $first, after: $after) {
                edges {
                    cursor
                    node {
                        __typename
                        id
                        createdAt
                        ... on AppSubscriptionSale {
                            chargeId
                            billingInterval
                            shop { id name myshopifyDomain }
                            grossAmount { amount currencyCode }
                            shopifyFee { amount currencyCode }
                            netAmount { amount currencyCode }
                        }
                        ... on AppOneTimeSale {
                            shop { id name myshopifyDomain }
                            grossAmount { amount currencyCode }
                            shopifyFee { amount currencyCode }
                            netAmount { amount currencyCode }
                        }
                        ... on AppUsageSale {
                            shop { id name myshopifyDomain }
                            grossAmount { amount currencyCode }
                            shopifyFee { amount currencyCode }
                            netAmount { amount currencyCode }
                        }
                        ... on AppSaleAdjustment {
                            shop { id name myshopifyDomain }
                            grossAmount { amount currencyCode }
                            shopifyFee { amount currencyCode }
                            netAmount { amount currencyCode }
                        }
                        ... on AppSaleCredit {
                            shop { id name myshopifyDomain }
                            grossAmount { amount currencyCode }
                            shopifyFee { amount currencyCode }
                            netAmount { amount currencyCode }
                        }
                    }
                }
                pageInfo { hasNextPage }
            }
        }
        GQL;

    public function __construct(
        private readonly PartnerClient $client,
        private readonly ShopRegistry $shops,
    ) {}

    /** @return array{fetched: int, shop_ids: list<int>} */
    public function sync(PartnerApp $app, CarbonInterface $since): array
    {
        $fetched = 0;
        $shopIds = [];
        $cursor = null;
        $latest = $app->transactions_synced_until;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $connection = $this->client->query(self::QUERY, [
                'appId' => $app->gid(),
                'createdAtMin' => $since->toIso8601String(),
                'types' => self::TYPES,
                'first' => self::PAGE_SIZE,
                'after' => $cursor,
            ])['transactions'];

            $lastCursor = null;

            foreach ($connection['edges'] as $edge) {
                $lastCursor = $edge['cursor'];
                $node = $edge['node'];

                if (! isset($node['netAmount'])) {
                    continue;
                }

                $shop = $this->shops->resolve($node['shop'] ?? []);
                $occurredAt = Carbon::parse($node['createdAt']);
                [$periodStart, $periodEnd] = PayoutPeriod::for($occurredAt);

                Transaction::updateOrCreate(
                    ['partner_transaction_id' => $node['id']],
                    [
                        'partner_app_id' => $app->id,
                        'shop_id' => $shop?->id,
                        'type' => $node['__typename'],
                        'occurred_at' => $occurredAt,
                        'gross_amount' => $node['grossAmount']['amount'] ?? 0,
                        'shopify_fee' => $node['shopifyFee']['amount'] ?? 0,
                        'net_amount' => $node['netAmount']['amount'],
                        'currency' => $node['netAmount']['currencyCode'] ?? null,
                        'charge_id' => $node['chargeId'] ?? null,
                        'billing_interval' => $node['billingInterval'] ?? null,
                        'payout_period_start' => $periodStart,
                        'payout_period_end' => $periodEnd,
                    ],
                );

                $fetched++;
                if ($shop !== null) {
                    $shopIds[$shop->id] = true;
                }

                if ($latest === null || $occurredAt->gt($latest)) {
                    $latest = $occurredAt;
                }
            }

            if (! $connection['pageInfo']['hasNextPage'] || $lastCursor === null) {
                break;
            }

            $cursor = $lastCursor;
        }

        $app->forceFill(['transactions_synced_until' => $latest ?? $since])->save();

        return ['fetched' => $fetched, 'shop_ids' => array_keys($shopIds)];
    }
}
