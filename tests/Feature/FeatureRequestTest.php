<?php

namespace Tests\Feature;

use App\Models\FeatureRequest;
use App\Models\PartnerApp;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_requests_and_development_moves_it_along(): void
    {
        $app = PartnerApp::create(['partner_app_id' => '1234', 'name' => 'Cart Ninja']);
        $admin = User::factory()->create();
        $dev = User::factory()->developer()->create();

        $this->actingAs($admin)->post(route('features.store'), [
            'title' => 'Upsell on cart drawer', 'description' => 'Brand wants it.', 'priority' => 'high',
            'partner_app_id' => $app->id, 'shop_domain' => 'https://Cool-Shop.myshopify.com/admin',
        ])->assertRedirect();

        $feature = FeatureRequest::sole();
        $this->assertSame('requested', $feature->status);
        $this->assertSame($admin->id, $feature->requested_by);
        $this->assertSame('cool-shop.myshopify.com', $feature->shop->myshopify_domain);

        $this->actingAs($dev);
        $this->get('/')->assertRedirect(route('features.index'));
        $this->get(route('features.index'))->assertOk()->assertSee('Upsell on cart drawer');
        $this->get(route('features.show', $feature))->assertOk()->assertSee('Update status');

        $this->patch(route('features.update', $feature), [
            'status' => 'in_progress', 'priority' => 'high', 'assignee_id' => $dev->id, 'note' => 'Starting today.',
        ])->assertRedirect();

        $feature->refresh();
        $this->assertSame('in_progress', $feature->status);
        $this->assertSame($dev->id, $feature->assignee_id);
        $update = $feature->updates->sole();
        $this->assertSame(['requested', 'in_progress', 'Starting today.'], [$update->from_status, $update->to_status, $update->body]);

        $this->patch(route('features.update', $feature), ['status' => 'done', 'priority' => 'high', 'assignee_id' => $dev->id]);
        $this->assertNotNull($feature->fresh()->completed_at);
        $this->assertSame(0, FeatureRequest::active()->count());
    }

    public function test_only_allowed_people_request_and_only_development_updates(): void
    {
        $setupPerson = User::factory()->team()->create(['team' => 'setup']);
        $feature = FeatureRequest::create(['title' => 'Bulk export', 'priority' => 'normal']);

        $this->actingAs($setupPerson);
        $this->get(route('features.index'))->assertOk()->assertDontSee('Request a feature');
        $this->get(route('features.create'))->assertForbidden();
        $this->post(route('features.store'), ['title' => 'x', 'priority' => 'normal'])->assertForbidden();
        $this->get(route('features.show', $feature))->assertOk()->assertDontSee('Update status');
        $this->patch(route('features.update', $feature), ['status' => 'done', 'priority' => 'normal'])->assertForbidden();
        $this->delete(route('features.destroy', $feature))->assertForbidden();

        // Anyone can comment; it shows in the history.
        $this->post(route('features.comment', $feature), ['body' => 'Two brands asked for this.'])->assertRedirect();
        $this->get(route('features.show', $feature))->assertSee('Two brands asked for this.');

        // An admin lets them request.
        $setupPerson->update(['can_request_features' => true]);
        $this->get(route('features.create'))->assertOk();
        $this->post(route('features.store'), ['title' => 'CSV import', 'priority' => 'normal'])->assertRedirect();
        $this->assertSame(2, FeatureRequest::count());
    }

    public function test_admin_sets_team_and_request_access(): void
    {
        $admin = User::factory()->create();
        $person = User::factory()->team()->create();

        $this->actingAs($admin)->patch(route('team.update', $person), [
            'role' => 'team', 'team' => 'development', 'can_request_features' => '1',
        ])->assertRedirect();

        $person->refresh();
        $this->assertSame('development', $person->team);
        $this->assertTrue($person->can_request_features);
        $this->assertTrue($person->isDeveloper());

        $this->patch(route('team.update', $person), ['role' => 'team', 'team' => 'setup']);
        $this->assertFalse($person->fresh()->can_request_features);
        $this->assertFalse($person->fresh()->isDeveloper());
    }
}
