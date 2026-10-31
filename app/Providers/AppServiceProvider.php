<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Partner\PartnerClient;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PartnerClient::class, fn () => PartnerClient::fromConfig());
    }

    public function boot(): void
    {
        Gate::define('admin', fn (User $user) => $user->isAdmin());
    }
}
