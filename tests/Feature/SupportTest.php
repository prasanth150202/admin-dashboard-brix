<?php

namespace Tests\Feature;

use App\Mail\TicketReply;
use App\Models\AppInstall;
use App\Models\PartnerApp;
use App\Models\Setup;
use App\Models\Shop;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Support\MailIngest;
use App\Services\Support\SetupPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SupportTest extends TestCase
{
    use RefreshDatabase;

    private PartnerApp $partnerApp;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        config(['support.api_token' => 'bot-secret', 'support.address' => 'support@thebrix.io', 'app.url' => 'https://admin-partners.thebrix.io']);

        $this->partnerApp = PartnerApp::create(['partner_app_id' => '1234', 'name' => 'Cart Ninja']);
        $this->shop = Shop::create(['myshopify_domain' => 'cool-shop.myshopify.com', 'name' => 'Cool Shop', 'kind' => Shop::KIND_CLIENT]);
    }

    public function test_team_members_cannot_see_money_pages(): void
    {
        $team = User::factory()->team()->create();
        $this->actingAs($team);

        $this->get('/')->assertRedirect(route('tickets.index'));
        $this->get('/payouts')->assertForbidden();
        $this->get('/apps')->assertForbidden();
        $this->get('/settings')->assertForbidden();
        $this->get('/team')->assertForbidden();
        $this->get('/stores/export')->assertForbidden();

        $this->get('/tickets')->assertOk();
        $this->get('/setups')->assertOk();
        $this->get(route('stores.show', $this->shop))->assertOk()->assertDontSee('Lifetime net');
    }

    public function test_support_form_creates_ticket_linked_to_store_and_app(): void
    {
        $this->get('/support?app=1234&shop=cool-shop')->assertOk()->assertSee('cool-shop.myshopify.com');

        $this->post('/support', [
            'name' => 'Asha', 'email' => 'Asha@Example.com', 'shop_domain' => 'https://cool-shop.myshopify.com/admin',
            'app' => $this->partnerApp->id, 'subject' => 'Widget missing', 'message' => 'The widget is gone from my cart.',
        ])->assertRedirect(route('support.thanks'));

        $ticket = Ticket::sole();
        $this->assertSame('form', $ticket->source);
        $this->assertSame($this->shop->id, $ticket->shop_id);
        $this->assertSame($this->partnerApp->id, $ticket->partner_app_id);
        $this->assertSame('asha@example.com', $ticket->requester_email);
        $this->assertSame('The widget is gone from my cart.', $ticket->messages()->sole()->body);
    }

    public function test_support_form_honeypot_drops_bots(): void
    {
        $this->post('/support', [
            'name' => 'Bot', 'email' => 'bot@example.com', 'shop_domain' => 'x', 'subject' => 'Buy', 'message' => 'Spam', 'website' => 'http://spam',
        ])->assertRedirect(route('support.thanks'));

        $this->assertSame(0, Ticket::count());
    }

    public function test_chatbot_api_needs_token_and_threads_by_conversation(): void
    {
        $payload = ['message' => 'My discount is not applying', 'email' => 'asha@example.com', 'shop_domain' => 'cool-shop', 'app' => 'cart ninja', 'conversation_id' => 'conv-1'];

        $this->postJson('/api/tickets', $payload)->assertUnauthorized();
        $this->postJson('/api/tickets', $payload, ['Authorization' => 'Bearer wrong'])->assertUnauthorized();

        $first = $this->postJson('/api/tickets', $payload, ['Authorization' => 'Bearer bot-secret'])
            ->assertCreated()->assertJsonPath('created', true);

        $this->postJson('/api/tickets', ['message' => 'Still broken', 'conversation_id' => 'conv-1'], ['Authorization' => 'Bearer bot-secret'])
            ->assertOk()->assertJsonPath('data.id', $first->json('data.id'))->assertJsonPath('created', false);

        $ticket = Ticket::sole();
        $this->assertSame('chatbot', $ticket->source);
        $this->assertSame('My discount is not applying', $ticket->subject);
        $this->assertSame($this->shop->id, $ticket->shop_id);
        $this->assertSame($this->partnerApp->id, $ticket->partner_app_id);
        $this->assertSame(2, $ticket->messages()->count());
    }

    public function test_reply_emails_merchant_and_their_answer_threads_back_and_reopens(): void
    {
        Mail::fake();
        $agent = User::factory()->team()->create(['name' => 'Ravi']);
        $ticket = app(\App\Services\Support\TicketIntake::class)->open('manual', [
            'subject' => 'Setup help', 'message' => 'Please help', 'email' => 'asha@example.com', 'shop_domain' => 'cool-shop.myshopify.com',
        ]);

        $this->actingAs($agent)->post(route('tickets.reply', $ticket), ['body' => 'Done, check now', 'status' => 'pending'])->assertRedirect();
        $this->actingAs($agent)->post(route('tickets.reply', $ticket), ['body' => 'Called them', 'internal' => '1'])->assertRedirect();

        Mail::assertSent(TicketReply::class, 1);
        Mail::assertSent(TicketReply::class, fn ($mail) => $mail->hasTo('asha@example.com') && str_contains($mail->envelope()->subject, "[#{$ticket->id}]"));
        $this->assertSame('pending', $ticket->fresh()->status);

        $sentId = $ticket->messages()->whereNotNull('user_id')->where('is_internal', false)->value('email_message_id');
        $ingest = app(MailIngest::class);

        // Threaded by In-Reply-To, without the subject tag.
        $landed = $ingest->ingest([
            'message_id' => '<reply-1@mail.example.com>', 'from_email' => 'asha@example.com', 'from_name' => 'Asha',
            'subject' => 'Re: Setup help', 'body' => "Works now, thanks!\n\nOn Mon, Ravi wrote:\n> Done, check now",
            'in_reply_to' => "<{$sentId}>",
        ]);
        $this->assertTrue($landed->is($ticket));
        $this->assertSame('open', $ticket->fresh()->status);
        $this->assertSame('Works now, thanks!', $ticket->fresh()->messages->last()->body);

        // The same email fetched twice is ignored.
        $this->assertNull($ingest->ingest(['message_id' => 'reply-1@mail.example.com', 'from_email' => 'asha@example.com', 'subject' => 'x', 'body' => 'x']));
        $this->assertSame(4, $ticket->messages()->count());
    }

    public function test_new_email_opens_ticket_and_finds_store_in_body(): void
    {
        $ticket = app(MailIngest::class)->ingest([
            'message_id' => '<new-1@mail.example.com>', 'from_email' => 'owner@example.com', 'from_name' => 'Owner',
            'subject' => 'Fwd: Billing question', 'body' => 'Our store cool-shop.myshopify.com was charged twice.',
        ]);

        $this->assertSame('email', $ticket->source);
        $this->assertSame('Billing question', $ticket->subject);
        $this->assertSame($this->shop->id, $ticket->shop_id);

        // A reply quoting the ticket number joins it.
        $again = app(MailIngest::class)->ingest([
            'message_id' => '<new-2@mail.example.com>', 'from_email' => 'owner@example.com', 'subject' => "Re: Billing question [#{$ticket->id}]", 'body' => 'Any update?',
        ]);
        $this->assertTrue($again->is($ticket));
        $this->assertSame(1, Ticket::count());
    }

    public function test_recent_installs_get_a_setup_with_the_app_checklist(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin)->put(route('apps.checklist.update', $this->partnerApp), ['steps' => "1. Enable app embed\n- Configure settings\n\n* Test order"])->assertRedirect();
        $this->assertSame(['Enable app embed', 'Configure settings', 'Test order'], $this->partnerApp->setupSteps()->pluck('title')->all());

        $test = Shop::create(['myshopify_domain' => 'dev.myshopify.com', 'kind' => Shop::KIND_TEST]);
        $old = Shop::create(['myshopify_domain' => 'old.myshopify.com', 'kind' => Shop::KIND_CLIENT]);
        foreach ([[$this->shop, now()->subDay()], [$test, now()->subDay()], [$old, now()->subYear()]] as [$shop, $at]) {
            AppInstall::create(['partner_app_id' => $this->partnerApp->id, 'shop_id' => $shop->id, 'status' => 'installed', 'last_installed_at' => $at]);
        }

        $this->assertSame(1, app(SetupPlanner::class)->planRecentInstalls());
        $this->assertSame(0, app(SetupPlanner::class)->planRecentInstalls());

        $setup = Setup::sole();
        $this->assertSame($this->shop->id, $setup->shop_id);
        $this->assertSame(3, $setup->tasks()->count());

        $task = $setup->tasks()->first();
        $this->post(route('setups.tasks.toggle', [$setup, $task]))->assertRedirect();
        $this->assertNotNull($task->fresh()->done_at);
        $this->assertSame('in_progress', $setup->fresh()->status);

        // Sales hands it to the team: who assigned it is recorded and shows on the dashboard.
        $sales = User::factory()->create(['role' => 'sales', 'name' => 'Sam Sales']);
        $agent = User::factory()->team()->create(['name' => 'Ravi']);
        $this->actingAs($sales)->patch(route('setups.update', $setup), ['status' => 'in_progress', 'assignee_id' => $agent->id])->assertRedirect();
        $setup->refresh();
        $this->assertSame($sales->id, $setup->assigned_by);
        $this->assertNotNull($setup->assigned_at);
        $this->actingAs($sales)->get('/')->assertRedirect(route('tickets.index'));
        $this->actingAs($admin)->get(route('setups.show', $setup))->assertSee('Assigned by Sam Sales');
        $people = $this->actingAs($admin)->get('/')->assertOk()->viewData('people')->keyBy('name');
        $this->assertSame(1, $people['Ravi']->open_setups);
        $this->assertSame(1, $people['Sam Sales']->handed_out);

        // Saving again without changing the assignee keeps the original assigner.
        $this->actingAs($admin)->patch(route('setups.update', $setup), ['status' => 'waiting', 'assignee_id' => $agent->id]);
        $this->assertSame($sales->id, $setup->fresh()->assigned_by);
        $this->actingAs($admin);

        // Older installs can still be started by hand.
        $this->post(route('setups.store'), ['shop_id' => $old->id, 'partner_app_id' => $this->partnerApp->id])->assertRedirect();
        $this->assertSame(2, Setup::count());
    }

    public function test_pages_render(): void
    {
        $admin = User::factory()->create();
        $ticket = app(\App\Services\Support\TicketIntake::class)->open('manual', ['subject' => 'Hello', 'message' => 'Hi', 'shop_domain' => 'cool-shop']);
        $setup = app(SetupPlanner::class)->start($this->shop, $this->partnerApp);

        $this->actingAs($admin);
        $this->get('/tickets')->assertOk()->assertSee('Hello');
        $this->get('/tickets?status=all&assignee=none&source=manual&q=%231')->assertOk()->assertSee('Hello');
        $this->get('/tickets/new?shop='.$this->shop->id)->assertOk()->assertSee('cool-shop.myshopify.com');
        $this->get(route('tickets.show', $ticket))->assertOk()->assertSee('Cool Shop');
        $this->get('/setups')->assertOk()->assertSee('Cool Shop');
        $this->get(route('setups.show', $setup))->assertOk();
        $this->get(route('stores.show', $this->shop))->assertOk()->assertSee('Hello')->assertSee('Lifetime net');
        $this->get('/team')->assertOk();
        $this->get(route('apps.checklist', $this->partnerApp))->assertOk();
        $this->get('/support')->assertOk();
        $this->get('/support/thanks')->assertOk();

        $this->post('/team', ['name' => 'Ravi', 'email' => 'Ravi@thebrix.io', 'role' => 'team', 'password' => 'long-enough-pw'])->assertRedirect();
        $this->assertFalse(User::where('email', 'ravi@thebrix.io')->sole()->isAdmin());

        // The last admin can't be demoted.
        $this->patch(route('team.update', $admin), ['role' => 'team'])->assertRedirect();
        $this->assertTrue($admin->fresh()->isAdmin());
    }
}
