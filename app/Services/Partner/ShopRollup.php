<?php

namespace App\Services\Partner;

use App\Models\AppEvent;
use App\Models\AppInstall;
use App\Models\Shop;
use App\Models\Transaction;

/**
 * Rebuilds the derived rows — app_installs and each shop's totals and
 * kind — from the raw events and transactions. Always a full replay per
 * shop, so it's safe to re-run and never drifts from the source rows.
 */
class ShopRollup
{
    /** Sale types count as "paid"; credits and adjustments only move the total. */
    private const SALE_TYPES = ['AppSubscriptionSale', 'AppOneTimeSale', 'AppUsageSale'];

    /** @param  list<int>|null  $shopIds  null rebuilds every shop */
    public function rebuild(?array $shopIds = null): int
    {
        $count = 0;

        Shop::query()
            ->when($shopIds !== null, fn ($q) => $q->whereIn('id', $shopIds))
            ->chunkById(200, function ($shops) use (&$count) {
                foreach ($shops as $shop) {
                    $this->rebuildShop($shop);
                    $count++;
                }
            });

        return $count;
    }

    public function rebuildShop(Shop $shop): void
    {
        $events = AppEvent::where('shop_id', $shop->id)->orderBy('occurred_at')->orderBy('id')->get();
        $netByApp = Transaction::where('shop_id', $shop->id)
            ->selectRaw('partner_app_id, SUM(net_amount) as net')
            ->groupBy('partner_app_id')
            ->pluck('net', 'partner_app_id');

        foreach ($events->groupBy('partner_app_id') as $appId => $appEvents) {
            AppInstall::updateOrCreate(
                ['partner_app_id' => $appId, 'shop_id' => $shop->id],
                $this->replay($appEvents) + ['lifetime_net' => $netByApp[$appId] ?? 0],
            );
        }

        $paidSales = Transaction::where('shop_id', $shop->id)
            ->whereIn('type', self::SALE_TYPES)
            ->where('net_amount', '>', 0);

        $isPaid = $paidSales->exists();
        $hasTestCharge = $events->contains(fn (AppEvent $e) => $e->charge_test === true);

        $shop->forceFill([
            'lifetime_net' => $netByApp->sum(),
            'is_paid' => $isPaid,
            'last_charge_at' => (clone $paidSales)->max('occurred_at'),
            'first_seen_at' => $events->first()?->occurred_at
                ?? Transaction::where('shop_id', $shop->id)->min('occurred_at'),
            'kind' => $this->classify($shop, $isPaid, $hasTestCharge),
        ])->save();
    }

    /**
     * Shopify gives no "development store" flag, so the kind is inferred:
     * a manual override wins; a real paid sale means a client; a test
     * charge (what dev stores get instead of real billing) means test;
     * anything else — free plans, trials, unpaid installs — stays unknown.
     */
    private function classify(Shop $shop, bool $isPaid, bool $hasTestCharge): string
    {
        return $shop->kind_override
            ?? ($isPaid ? Shop::KIND_CLIENT : ($hasTestCharge ? Shop::KIND_TEST : Shop::KIND_UNKNOWN));
    }

    /** @param  iterable<AppEvent>  $events  one app's events for one shop, oldest first */
    private function replay(iterable $events): array
    {
        $state = [
            'status' => AppInstall::STATUS_UNINSTALLED,
            'first_installed_at' => null,
            'last_installed_at' => null,
            'uninstalled_at' => null,
            'install_count' => 0,
            'uninstall_reason' => null,
            'plan_name' => null,
            'plan_amount' => null,
            'plan_currency' => null,
            'plan_test' => null,
            'plan_status' => null,
            'plan_billing_on' => null,
        ];
        $planChargeId = null;

        foreach ($events as $event) {
            switch ($event->type) {
                case AppEvent::INSTALLED:
                    $state['status'] = AppInstall::STATUS_INSTALLED;
                    $state['first_installed_at'] ??= $event->occurred_at;
                    $state['last_installed_at'] = $event->occurred_at;
                    $state['uninstalled_at'] = null;
                    $state['uninstall_reason'] = null;
                    $state['install_count']++;
                    break;

                case AppEvent::REACTIVATED:
                    $state['status'] = AppInstall::STATUS_INSTALLED;
                    break;

                case AppEvent::DEACTIVATED:
                    $state['status'] = AppInstall::STATUS_DEACTIVATED;
                    break;

                case AppEvent::UNINSTALLED:
                    $state['status'] = AppInstall::STATUS_UNINSTALLED;
                    $state['uninstalled_at'] = $event->occurred_at;
                    $state['uninstall_reason'] = $event->reason;
                    // Uninstalling ends the subscription even when the
                    // matching cancel event arrives late or not at all.
                    if ($state['plan_status'] === 'active' || $state['plan_status'] === 'frozen') {
                        $state['plan_status'] = 'cancelled';
                    }
                    break;

                case 'SUBSCRIPTION_CHARGE_ACTIVATED':
                    $planChargeId = $event->charge_id;
                    $state['plan_name'] = $event->charge_name;
                    $state['plan_amount'] = $event->charge_amount;
                    $state['plan_currency'] = $event->charge_currency;
                    $state['plan_test'] = $event->charge_test;
                    $state['plan_billing_on'] = $event->billing_on;
                    $state['plan_status'] = 'active';
                    break;

                case 'SUBSCRIPTION_CHARGE_CANCELED':
                    // On a plan change the new charge activates and the old
                    // one is cancelled — only the current charge ends the plan.
                    if ($planChargeId === null || $planChargeId === $event->charge_id) {
                        $state['plan_status'] = 'cancelled';
                    }
                    break;

                case 'SUBSCRIPTION_CHARGE_FROZEN':
                    if ($planChargeId === $event->charge_id) {
                        $state['plan_status'] = 'frozen';
                    }
                    break;

                case 'SUBSCRIPTION_CHARGE_UNFROZEN':
                    if ($planChargeId === $event->charge_id) {
                        $state['plan_status'] = 'active';
                    }
                    break;

                case 'SUBSCRIPTION_CHARGE_DECLINED':
                case 'SUBSCRIPTION_CHARGE_EXPIRED':
                    // A pending upgrade that was declined leaves the
                    // existing plan untouched.
                    if ($state['plan_status'] !== 'active') {
                        $state['plan_status'] = $event->type === 'SUBSCRIPTION_CHARGE_DECLINED' ? 'declined' : 'expired';
                    }
                    break;
            }
        }

        return $state;
    }
}
