<?php

namespace App\Services\Partner;

use App\Models\AppEvent;
use App\Models\PartnerApp;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Pulls app(id).events — installs, uninstalls, deactivations and
 * subscription charge changes — for one app and upserts them.
 */
class EventSync
{
    private const PAGE_SIZE = 100;

    /** Hard stop so a misbehaving cursor can't loop forever (200k events). */
    private const MAX_PAGES = 2000;

    private const QUERY = <<<'GQL'
        query($appId: ID!, $occurredAtMin: DateTime!, $first: Int!, $after: String) {
            app(id: $appId) {
                events(occurredAtMin: $occurredAtMin, first: $first, after: $after) {
                    edges {
                        cursor
                        node {
                            type
                            occurredAt
                            shop { id name myshopifyDomain }
                            ... on RelationshipUninstalled { reason description }
                            ... on AppSubscriptionEvent {
                                charge { id name test billingOn amount { amount currencyCode } }
                            }
                        }
                    }
                    pageInfo { hasNextPage }
                }
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
        $latest = $app->events_synced_until;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $data = $this->client->query(self::QUERY, [
                'appId' => $app->gid(),
                'occurredAtMin' => $since->toIso8601String(),
                'first' => self::PAGE_SIZE,
                'after' => $cursor,
            ]);

            if (($data['app'] ?? null) === null) {
                throw new PartnerApiException("App {$app->partner_app_id} was not found in this Partner organization.");
            }

            $connection = $data['app']['events'];
            $lastCursor = null;

            foreach ($connection['edges'] as $edge) {
                $lastCursor = $edge['cursor'];
                $node = $edge['node'];

                $shop = $this->shops->resolve($node['shop'] ?? []);
                if ($shop === null) {
                    continue;
                }

                $occurredAt = Carbon::parse($node['occurredAt']);
                $charge = $node['charge'] ?? null;

                AppEvent::updateOrCreate(
                    ['dedupe_key' => $this->dedupeKey($app, $node)],
                    [
                        'partner_app_id' => $app->id,
                        'shop_id' => $shop->id,
                        'type' => $node['type'],
                        'occurred_at' => $occurredAt,
                        'charge_id' => $charge['id'] ?? null,
                        'charge_name' => $charge['name'] ?? null,
                        'charge_amount' => $charge['amount']['amount'] ?? null,
                        'charge_currency' => $charge['amount']['currencyCode'] ?? null,
                        'charge_test' => $charge['test'] ?? null,
                        'billing_on' => isset($charge['billingOn']) ? Carbon::parse($charge['billingOn']) : null,
                        'reason' => isset($node['reason']) ? mb_strimwidth($node['reason'], 0, 250) : null,
                        'description' => $node['description'] ?? null,
                        'payload' => $node,
                    ],
                );

                $fetched++;
                $shopIds[$shop->id] = true;

                if ($latest === null || $occurredAt->gt($latest)) {
                    $latest = $occurredAt;
                }
            }

            // PageInfo here has no endCursor, so the next page starts
            // after the last edge's own cursor.
            if (! $connection['pageInfo']['hasNextPage'] || $lastCursor === null) {
                break;
            }

            $cursor = $lastCursor;
        }

        $app->forceFill(['events_synced_until' => $latest ?? $since])->save();

        return ['fetched' => $fetched, 'shop_ids' => array_keys($shopIds)];
    }

    /**
     * AppEvent carries no ID, so an event is identified by what it is:
     * app, shop, type, exact timestamp and (for charge events) the charge.
     */
    private function dedupeKey(PartnerApp $app, array $node): string
    {
        return hash('sha256', implode('|', [
            $app->partner_app_id,
            strtolower($node['shop']['myshopifyDomain'] ?? ''),
            $node['type'],
            $node['occurredAt'],
            $node['charge']['id'] ?? '',
        ]));
    }
}
