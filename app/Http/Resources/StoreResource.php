<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A store as the API shows it: identity, type and installs. Never any
 * earnings or plan prices — the API token is shared with the chatbot.
 *
 * @mixin \App\Models\Shop
 */
class StoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'domain' => $this->myshopify_domain,
            'name' => $this->name,
            'type' => ['client' => 'client', 'test' => 'test'][$this->kind] ?? 'unpaid',
            'installs' => $this->whenLoaded('installs', fn () => $this->installs->map(fn ($install) => [
                'app' => AppResource::make($install->app),
                'status' => $install->status,
                'plan' => $install->plan_status === 'active' ? $install->plan_name : null,
                'first_installed_at' => $install->first_installed_at?->toIso8601String(),
                'uninstalled_at' => $install->uninstalled_at?->toIso8601String(),
            ])->values()),
        ];
    }
}
