<?php

namespace App\Http\Controllers;

use App\Models\AppInstall;
use App\Models\PartnerApp;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StoresController extends Controller
{
    private const SORTS = ['lifetime_net', 'last_charge_at', 'first_seen_at', 'myshopify_domain'];

    public function index(Request $request)
    {
        $shops = $this->filtered($request)
            ->with(['installs.app'])
            ->paginate(50)
            ->withQueryString();

        return view('stores.index', [
            'shops' => $shops,
            'apps' => PartnerApp::orderBy('name')->get(),
            'filters' => $request->only(['q', 'app', 'status', 'kind', 'paid', 'sort']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->filtered($request)->with(['installs.app']);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Store', 'Domain', 'Type', 'Paid', 'Apps installed', 'Apps uninstalled', 'Current plans', 'Lifetime net', 'Last charge', 'First seen']);

            $query->chunk(500, function ($shops) use ($out) {
                foreach ($shops as $shop) {
                    $installed = $shop->installs->where('status', AppInstall::STATUS_INSTALLED);
                    fputcsv($out, [
                        $shop->name,
                        $shop->myshopify_domain,
                        $shop->kind,
                        $shop->is_paid ? 'yes' : 'no',
                        $installed->map(fn ($i) => $i->app->name)->implode('; '),
                        $shop->installs->where('status', '!=', AppInstall::STATUS_INSTALLED)->map(fn ($i) => $i->app->name)->implode('; '),
                        $installed->filter(fn ($i) => $i->plan_status === 'active')->map(fn ($i) => "{$i->app->name}: {$i->plan_name} ({$i->plan_amount})".($i->plan_test ? ' [test]' : ''))->implode('; '),
                        $shop->lifetime_net,
                        $shop->last_charge_at?->toDateString(),
                        $shop->first_seen_at?->toDateString(),
                    ]);
                }
            });

            fclose($out);
        }, 'stores-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function show(Shop $shop)
    {
        $shop->load(['installs.app']);

        return view('stores.show', [
            'shop' => $shop,
            'transactions' => $shop->transactions()->with('app')->latest('occurred_at')->paginate(25, ['*'], 'tx'),
            'events' => $shop->events()->with('app')->latest('occurred_at')->limit(100)->get(),
        ]);
    }

    public function update(Request $request, Shop $shop)
    {
        $data = $request->validate([
            'kind_override' => ['nullable', Rule::in([Shop::KIND_CLIENT, Shop::KIND_TEST])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $shop->fill($data)->save();
        app(\App\Services\Partner\ShopRollup::class)->rebuildShop($shop);

        return back()->with('status', 'Store updated.');
    }

    private function filtered(Request $request): Builder
    {
        $appId = $request->integer('app') ?: null;
        $status = $request->input('status');
        $sort = in_array($request->input('sort'), self::SORTS, true) ? $request->input('sort') : 'lifetime_net';

        return Shop::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $request->input('q')).'%';
                $q->where(fn ($q) => $q->where('myshopify_domain', 'like', $term)->orWhere('name', 'like', $term));
            })
            ->when(in_array($request->input('kind'), [Shop::KIND_CLIENT, Shop::KIND_TEST, Shop::KIND_UNKNOWN], true),
                fn ($q) => $q->where('kind', $request->input('kind')))
            ->when($request->input('paid') === 'yes', fn ($q) => $q->where('is_paid', true))
            ->when($request->input('paid') === 'no', fn ($q) => $q->where('is_paid', false))
            ->when($appId && ! $status, fn ($q) => $q->whereHas('installs', fn ($q) => $q->where('partner_app_id', $appId)))
            // "installed" = installed in the chosen app (or in any app);
            // "uninstalled" = no longer installed in it (or in any app).
            ->when($status === 'installed', fn ($q) => $q->whereHas('installs', fn ($q) => $q
                ->where('status', AppInstall::STATUS_INSTALLED)
                ->when($appId, fn ($q) => $q->where('partner_app_id', $appId))))
            ->when($status === 'uninstalled', fn ($q) => $q
                ->whereHas('installs', fn ($q) => $q->when($appId, fn ($q) => $q->where('partner_app_id', $appId)))
                ->whereDoesntHave('installs', fn ($q) => $q
                    ->where('status', AppInstall::STATUS_INSTALLED)
                    ->when($appId, fn ($q) => $q->where('partner_app_id', $appId))))
            ->orderBy($sort, $sort === 'myshopify_domain' ? 'asc' : 'desc')
            ->orderBy('id');
    }
}
