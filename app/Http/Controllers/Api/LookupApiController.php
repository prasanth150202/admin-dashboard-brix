<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AppResource;
use App\Http\Resources\StoreResource;
use App\Http\Resources\UserResource;
use App\Models\PartnerApp;
use App\Models\Shop;
use App\Models\User;
use App\Services\Support\TicketIntake;
use Illuminate\Http\Request;

/** Read-only lists for finding the IDs, domains and names the other endpoints take. */
class LookupApiController extends Controller
{
    public function apps()
    {
        return AppResource::collection(PartnerApp::orderBy('name')->get());
    }

    public function users()
    {
        return UserResource::collection(User::orderBy('name')->get());
    }

    public function stores(Request $request)
    {
        $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        $stores = Shop::query()
            ->with('installs.app')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $request->input('q')).'%';
                $q->where(fn ($q) => $q->where('myshopify_domain', 'like', $term)->orWhere('name', 'like', $term));
            })
            ->orderBy('myshopify_domain')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return StoreResource::collection($stores);
    }

    /** Looks a store up by its ID or its domain ("cool-shop" works too). */
    public function store(string $store): StoreResource
    {
        $shop = ctype_digit($store)
            ? Shop::find((int) $store)
            : Shop::where('myshopify_domain', TicketIntake::normalizeDomain($store))->first();

        abort_if($shop === null, 404, 'Store not found.');

        return StoreResource::make($shop->load('installs.app'));
    }
}
