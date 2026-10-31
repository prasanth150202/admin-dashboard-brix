<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Models\PartnerApp;
use App\Models\SyncRun;
use App\Services\Partner\PartnerApiException;
use App\Services\Partner\PartnerClient;
use App\Services\Partner\ShopRollup;
use App\Services\Partner\SyncRunner;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        return view('settings.index', [
            'apps' => PartnerApp::withCount('installs')->orderBy('name')->get(),
            'runs' => SyncRun::latest('started_at')->limit(15)->get(),
            'configured' => PartnerClient::fromConfig()->isConfigured(),
            'orgId' => config('partner.org_id'),
            'apiVersion' => config('partner.api_version'),
            'schedule' => config('partner.payout_schedule'),
            'backfillFrom' => config('partner.backfill_from'),
            'apiKeys' => ApiKey::with('creator')->latest('id')->get(),
        ]);
    }

    /** The plain key is flashed once for display; only its hash is stored. */
    public function storeApiKey(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);

        [$key, $token] = ApiKey::generate($data['name'], $request->user());

        return redirect()->to(route('settings').'#api-keys')
            ->with('new_api_key', ['name' => $key->name, 'token' => $token]);
    }

    public function destroyApiKey(ApiKey $key)
    {
        $key->delete();

        return redirect()->to(route('settings').'#api-keys')->with('status', "Revoked the “{$key->name}” key. Anything using it is now locked out.");
    }

    /** Adds an app by its Partner app ID, confirming it exists in the org first. */
    public function storeApp(Request $request, PartnerClient $client)
    {
        $data = $request->validate([
            'partner_app_id' => ['required', 'string', 'max:64'],
        ]);

        // Accept "1234", "gid://partners/App/1234" or a Partner Dashboard URL.
        preg_match('/(\d{3,})\D*$/', $data['partner_app_id'], $m);
        $id = $m[1] ?? null;

        if ($id === null) {
            return back()->withErrors(['partner_app_id' => 'That doesn’t look like a Partner app ID.'])->withInput();
        }

        try {
            $app = $client->query('query($id: ID!) { app(id: $id) { id name apiKey } }', ['id' => "gid://partners/App/{$id}"])['app'] ?? null;
        } catch (PartnerApiException $e) {
            return back()->withErrors(['partner_app_id' => $e->getMessage()])->withInput();
        }

        if ($app === null) {
            return back()->withErrors(['partner_app_id' => "No app {$id} in this Partner organization."])->withInput();
        }

        PartnerApp::updateOrCreate(['partner_app_id' => $id], ['name' => $app['name'], 'api_key' => $app['apiKey'] ?? null]);

        return back()->with('status', "Added {$app['name']}. Run a sync to pull its data.");
    }

    public function destroyApp(PartnerApp $app, ShopRollup $rollup)
    {
        $shopIds = $app->installs()->pluck('shop_id')->all();
        $app->delete();
        $rollup->rebuild($shopIds);

        return back()->with('status', "Removed {$app->name} and its synced data.");
    }

    public function test(PartnerClient $client)
    {
        try {
            $client->query('{ transactions(first: 1) { edges { node { id } } } }');
        } catch (PartnerApiException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Connected — the token can read transactions.');
    }

    public function sync(Request $request, SyncRunner $runner)
    {
        // A first full backfill can take minutes; on most hosts it's
        // better run once over SSH with `php artisan partner:sync --full`.
        set_time_limit(0);

        $run = $runner->run($request->boolean('full'), 'manual');

        return $run->status === 'ok'
            ? back()->with('status', "Sync finished: {$run->events_fetched} events, {$run->transactions_fetched} transactions. {$run->message}")
            : back()->with('error', "Sync finished with errors: {$run->message}");
    }
}
