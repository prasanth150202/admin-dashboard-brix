<?php

namespace Tests\Feature;

use App\Models\AppEvent;
use App\Models\AppInstall;
use App\Models\PartnerApp;
use App\Models\Shop;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Partner\PayoutPeriod;
use App\Services\Partner\SyncRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PartnerSyncTest extends TestCase
{
    use RefreshDatabase;

    private PartnerApp $partnerApp;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'partner.org_id' => '999',
            'partner.api_token' => 'prtapi_test',
            'partner.backfill_from' => '2026-01-01',
            'partner.payout_schedule' => 'semimonthly',
        ]);

        $this->partnerApp = PartnerApp::create(['partner_app_id' => '1234', 'name' => 'Cart Ninja']);

        Http::fake(['partners.shopify.com/*' => function (Request $request) {
            return str_contains($request['query'], 'transactions(')
                ? Http::response(['data' => ['transactions' => $this->transactions()]])
                : Http::response(['data' => ['app' => ['events' => $this->events()]]]);
        }]);
    }

    public function test_sync_builds_installs_revenue_and_store_types(): void
    {
        $run = app(SyncRunner::class)->run();

        $this->assertSame('ok', $run->status, (string) $run->message);
        $this->assertSame(6, $run->events_fetched);
        $this->assertSame(2, $run->transactions_fetched);

        $client = Shop::where('myshopify_domain', 'client.myshopify.com')->firstOrFail();
        $this->assertSame(Shop::KIND_CLIENT, $client->kind);
        $this->assertTrue($client->is_paid);
        $this->assertSame('35.00', $client->lifetime_net);

        $install = AppInstall::where('shop_id', $client->id)->firstOrFail();
        $this->assertSame(AppInstall::STATUS_INSTALLED, $install->status);
        $this->assertSame('active', $install->plan_status);
        $this->assertSame('Pro', $install->plan_name);

        $test = Shop::where('myshopify_domain', 'dev.myshopify.com')->firstOrFail();
        $this->assertSame(Shop::KIND_TEST, $test->kind);
        $this->assertFalse($test->is_paid);

        $gone = Shop::where('myshopify_domain', 'gone.myshopify.com')->firstOrFail();
        $this->assertSame(Shop::KIND_UNKNOWN, $gone->kind);
        $gi = AppInstall::where('shop_id', $gone->id)->firstOrFail();
        $this->assertSame(AppInstall::STATUS_UNINSTALLED, $gi->status);
        $this->assertSame('Too expensive', $gi->uninstall_reason);

        $sale = Transaction::where('partner_transaction_id', 'gid://partners/AppSubscriptionSale/1')->firstOrFail();
        $this->assertSame('2026-10-16', $sale->payout_period_start->toDateString());
        $this->assertSame('2026-10-31', $sale->payout_period_end->toDateString());
    }

    public function test_resync_does_not_duplicate_rows(): void
    {
        app(SyncRunner::class)->run();
        app(SyncRunner::class)->run();
        app(SyncRunner::class)->run(full: true);

        $this->assertSame(6, AppEvent::count());
        $this->assertSame(2, Transaction::count());
        $this->assertSame(3, AppInstall::count());
        $this->assertSame('35.00', Shop::where('myshopify_domain', 'client.myshopify.com')->value('lifetime_net'));
    }

    public function test_manual_override_wins(): void
    {
        app(SyncRunner::class)->run();
        $user = User::factory()->create();
        $shop = Shop::where('myshopify_domain', 'client.myshopify.com')->firstOrFail();

        $this->actingAs($user)->patch(route('stores.update', $shop), ['kind_override' => 'test'])->assertRedirect();
        $this->assertSame(Shop::KIND_TEST, $shop->fresh()->kind);

        app(SyncRunner::class)->run();
        $this->assertSame(Shop::KIND_TEST, $shop->fresh()->kind);
    }

    public function test_every_page_renders(): void
    {
        app(SyncRunner::class)->run();
        $user = User::factory()->create();
        $shop = Shop::where('myshopify_domain', 'client.myshopify.com')->firstOrFail();

        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk();

        $this->actingAs($user);
        $this->get('/')->assertOk()->assertSee('Installed stores');
        $this->get('/?app='.$this->partnerApp->id)->assertOk();
        $this->get('/apps')->assertOk()->assertSee('Cart Ninja');
        $this->get('/stores')->assertOk()->assertSee('client.myshopify.com');
        $this->get('/stores?status=uninstalled')->assertOk()->assertSee('gone.myshopify.com')->assertDontSee('client.myshopify.com');
        $this->get('/stores?kind=test')->assertOk()->assertSee('dev.myshopify.com')->assertDontSee('client.myshopify.com');
        $this->get('/stores/export')->assertOk();
        $this->get(route('stores.show', $shop))->assertOk()->assertSee('Pro');
        $this->get('/payouts')->assertOk();
        $this->get('/payouts/2026-10-16')->assertOk()->assertSee('client.myshopify.com');
        $this->get('/payouts/2026-10-16/export')->assertOk();
        $this->get('/settings')->assertOk()->assertSee('1234');
    }

    public function test_payout_periods(): void
    {
        $at = fn ($d) => array_map(fn ($c) => $c->toDateString(), PayoutPeriod::for(Carbon::parse($d)));

        $this->assertSame(['2026-02-01', '2026-02-15'], $at('2026-02-15 23:59:00'));
        $this->assertSame(['2026-02-16', '2026-02-28'], $at('2026-02-16 00:00:00'));
        $this->assertSame(['2026-10-01', '2026-10-31'], array_map(fn ($c) => $c->toDateString(), PayoutPeriod::for(Carbon::parse('2026-10-20'), 'monthly')));
        $this->assertSame(['2026-10-26', '2026-11-01'], array_map(fn ($c) => $c->toDateString(), PayoutPeriod::for(Carbon::parse('2026-10-29'), 'weekly')));
    }

    private function events(): array
    {
        $shop = fn ($d) => ['id' => 'gid://partners/Shop/'.crc32($d), 'name' => ucfirst(strtok($d, '.')), 'myshopifyDomain' => $d];
        $charge = fn ($id, $test) => ['id' => "gid://shopify/AppSubscription/{$id}", 'name' => 'Pro', 'test' => $test, 'billingOn' => '2026-11-20T00:00:00Z', 'amount' => ['amount' => '19.99', 'currencyCode' => 'USD']];

        $nodes = [
            ['type' => 'RELATIONSHIP_INSTALLED', 'occurredAt' => '2026-09-01T10:00:00Z', 'shop' => $shop('client.myshopify.com')],
            ['type' => 'SUBSCRIPTION_CHARGE_ACTIVATED', 'occurredAt' => '2026-09-01T10:05:00Z', 'shop' => $shop('client.myshopify.com'), 'charge' => $charge(1, false)],
            ['type' => 'RELATIONSHIP_INSTALLED', 'occurredAt' => '2026-09-02T10:00:00Z', 'shop' => $shop('dev.myshopify.com')],
            ['type' => 'SUBSCRIPTION_CHARGE_ACTIVATED', 'occurredAt' => '2026-09-02T10:05:00Z', 'shop' => $shop('dev.myshopify.com'), 'charge' => $charge(2, true)],
            ['type' => 'RELATIONSHIP_INSTALLED', 'occurredAt' => '2026-09-03T10:00:00Z', 'shop' => $shop('gone.myshopify.com')],
            ['type' => 'RELATIONSHIP_UNINSTALLED', 'occurredAt' => '2026-09-10T10:00:00Z', 'shop' => $shop('gone.myshopify.com'), 'reason' => 'Too expensive', 'description' => null],
        ];

        return [
            'edges' => array_map(fn ($n, $i) => ['cursor' => "c{$i}", 'node' => $n], $nodes, array_keys($nodes)),
            'pageInfo' => ['hasNextPage' => false],
        ];
    }

    private function transactions(): array
    {
        $money = fn ($a) => ['amount' => $a, 'currencyCode' => 'USD'];
        $shop = ['id' => 'gid://partners/Shop/1', 'name' => 'Client', 'myshopifyDomain' => 'client.myshopify.com'];

        return [
            'edges' => [
                ['cursor' => 't1', 'node' => [
                    '__typename' => 'AppSubscriptionSale', 'id' => 'gid://partners/AppSubscriptionSale/1', 'createdAt' => '2026-10-20T12:00:00Z',
                    'chargeId' => 'gid://shopify/AppSubscription/1', 'billingInterval' => 'EVERY_30_DAYS', 'shop' => $shop,
                    'grossAmount' => $money('25.00'), 'shopifyFee' => $money('5.00'), 'netAmount' => $money('20.00'),
                ]],
                ['cursor' => 't2', 'node' => [
                    '__typename' => 'AppUsageSale', 'id' => 'gid://partners/AppUsageSale/2', 'createdAt' => '2026-10-21T12:00:00Z', 'shop' => $shop,
                    'grossAmount' => $money('18.75'), 'shopifyFee' => $money('3.75'), 'netAmount' => $money('15.00'),
                ]],
            ],
            'pageInfo' => ['hasNextPage' => false],
        ];
    }
}
