<?php

namespace Tests\Feature;

use App\Models\AppInstall;
use App\Models\PartnerApp;
use App\Models\Setup;
use App\Models\Shop;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    private PartnerApp $partnerApp;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        config(['support.api_token' => 'bot-secret']);
        $this->withHeaders(['Authorization' => 'Bearer bot-secret']);

        $this->partnerApp = PartnerApp::create(['partner_app_id' => '1234', 'name' => 'Cart Ninja']);
        $this->partnerApp->setupSteps()->createMany([['title' => 'Enable embed', 'position' => 1], ['title' => 'Test order', 'position' => 2]]);
        $this->shop = Shop::create(['myshopify_domain' => 'cool-shop.myshopify.com', 'name' => 'Cool Shop', 'kind' => Shop::KIND_CLIENT, 'lifetime_net' => 500]);
        AppInstall::create(['partner_app_id' => $this->partnerApp->id, 'shop_id' => $this->shop->id, 'status' => 'installed', 'plan_name' => 'Pro', 'plan_status' => 'active', 'plan_amount' => 49]);
    }

    public function test_every_endpoint_needs_the_token(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer nope']);

        foreach (['/api/tickets', '/api/setups', '/api/stores', '/api/apps', '/api/users'] as $url) {
            $this->get($url)->assertUnauthorized()->assertJson(['message' => 'Unauthorized.']);
        }
    }

    public function test_keys_made_in_settings_work_until_revoked(): void
    {
        config(['support.api_token' => null]);
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('settings.api-keys.store'), ['name' => 'Website chatbot'])
            ->assertRedirect()->assertSessionHas('new_api_key');
        $token = session('new_api_key')['token'];
        $key = \App\Models\ApiKey::sole();
        $this->assertNotSame($token, $key->token_hash);
        $this->actingAs($admin)->get(route('settings'))->assertOk()->assertSee('Website chatbot')->assertSee($key->prefix);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])->getJson('/api/apps')->assertOk();
        $this->assertNotNull($key->fresh()->last_used_at);

        // Team members can't make keys.
        $this->actingAs(User::factory()->team()->create())->post(route('settings.api-keys.store'), ['name' => 'x'])->assertForbidden();

        $this->actingAs($admin)->delete(route('settings.api-keys.destroy', $key))->assertRedirect();
        $this->withHeaders(['Authorization' => "Bearer {$token}"])->getJson('/api/apps')->assertUnauthorized();
    }

    public function test_ticket_crud(): void
    {
        $agent = User::factory()->team()->create();

        // Create — no Accept header, still JSON.
        $id = $this->post('/api/tickets', ['message' => 'Widget gone', 'subject' => 'Widget', 'email' => 'a@example.com', 'shop_domain' => 'cool-shop', 'app' => '1234'])
            ->assertCreated()
            ->assertJsonPath('data.store.domain', 'cool-shop.myshopify.com')
            ->assertJsonPath('data.app.name', 'Cart Ninja')
            ->assertJsonPath('data.messages.0.from', 'merchant')
            ->json('data.id');

        $this->post('/api/tickets', ['subject' => 'No message'])->assertUnprocessable()->assertJsonValidationErrors('message');

        // Read
        $this->getJson('/api/tickets?status=open&email=A@example.com')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1);
        $this->getJson('/api/tickets?shop_domain=other-shop')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/tickets/{$id}")->assertOk()->assertJsonPath('data.subject', 'Widget');
        $this->getJson('/api/tickets/999')->assertNotFound();

        // Update (partial)
        $this->patchJson("/api/tickets/{$id}", ['status' => 'resolved', 'assignee_id' => $agent->id, 'priority' => 'high'])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.assignee.id', $agent->id)
            ->assertJsonPath('data.subject', 'Widget');
        $this->assertNotNull(Ticket::find($id)->resolved_at);

        // Messages: a merchant message reopens it.
        $this->postJson("/api/tickets/{$id}/messages", ['message' => 'Broken again'])->assertCreated()->assertJsonPath('data.body', 'Broken again');
        $this->getJson("/api/tickets/{$id}/messages")->assertOk()->assertJsonCount(2, 'data');
        $this->assertSame('open', Ticket::find($id)->status);

        // Delete
        $this->deleteJson("/api/tickets/{$id}")->assertNoContent();
        $this->assertSame(0, Ticket::count());
    }

    public function test_setup_and_task_crud(): void
    {
        $this->postJson('/api/setups', ['shop_domain' => 'missing-shop', 'app' => 'Cart Ninja'])->assertUnprocessable()->assertJsonValidationErrors('shop_domain');

        $id = $this->postJson('/api/setups', ['shop_domain' => 'cool-shop.myshopify.com', 'app' => 'cart ninja', 'due_on' => '2026-11-15'])
            ->assertCreated()
            ->assertJsonPath('created', true)
            ->assertJsonPath('data.progress', ['done' => 0, 'total' => 2])
            ->assertJsonPath('data.due_on', '2026-11-15')
            ->json('data.id');

        // Starting it again returns the same setup.
        $this->postJson('/api/setups', ['shop_domain' => 'cool-shop', 'app' => '1234'])->assertOk()->assertJsonPath('data.id', $id)->assertJsonPath('created', false);

        $taskId = $this->postJson("/api/setups/{$id}/tasks", ['title' => 'Call merchant'])->assertCreated()->json('data.id');
        $first = Setup::find($id)->tasks()->first();

        $this->patchJson("/api/setups/{$id}/tasks/{$first->id}", ['done' => true])->assertOk()->assertJsonPath('data.done', true);
        $this->assertSame('in_progress', Setup::find($id)->status);

        $this->deleteJson("/api/setups/{$id}/tasks/{$taskId}")->assertNoContent();
        $this->getJson("/api/setups/{$id}")->assertOk()->assertJsonPath('data.progress', ['done' => 1, 'total' => 2]);

        // A task from another setup can't be reached through this one.
        $other = Setup::create(['shop_id' => Shop::create(['myshopify_domain' => 'b.myshopify.com'])->id, 'partner_app_id' => $this->partnerApp->id]);
        $foreign = $other->tasks()->create(['title' => 'x']);
        $this->patchJson("/api/setups/{$id}/tasks/{$foreign->id}", ['done' => true])->assertNotFound();

        $this->patchJson("/api/setups/{$id}", ['status' => 'done'])->assertOk()->assertJsonPath('data.status', 'done');
        $this->getJson('/api/setups?status=active')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/setups?shop_domain=cool-shop')->assertOk()->assertJsonCount(1, 'data');

        $this->deleteJson("/api/setups/{$id}")->assertNoContent();
        $this->assertNull(Setup::find($id));
    }

    public function test_lookups_never_expose_money(): void
    {
        User::factory()->create(['name' => 'Ravi']);

        $this->getJson('/api/apps')->assertOk()->assertJsonPath('data.0.partner_app_id', '1234');
        $this->getJson('/api/users')->assertOk()->assertJsonPath('data.0.name', 'Ravi');
        $this->getJson('/api/stores?q=cool')->assertOk()->assertJsonPath('data.0.domain', 'cool-shop.myshopify.com');
        $store = $this->getJson('/api/stores/cool-shop')->assertOk()
            ->assertJsonPath('data.type', 'client')
            ->assertJsonPath('data.installs.0.plan', 'Pro');
        $this->getJson("/api/stores/{$this->shop->id}")->assertOk();
        $this->getJson('/api/stores/nope')->assertNotFound();

        $this->assertStringNotContainsString('500', $store->getContent());
        $this->assertStringNotContainsString('49', $store->getContent());
    }
}
