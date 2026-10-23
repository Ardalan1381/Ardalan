<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(function (User $user, string $ability) {
            return $user->hasPermission($ability) ?: null;
        });

        $this->configureRateLimiting();
    }

    /**
     * Public, unauthenticated endpoints that write to the database (or could
     * be scraped) get their own named limiters instead of relying on the
     * default web middleware, which applies no throttling at all.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('order-tracking', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        RateLimiter::for('waiter-call', fn (Request $request) => Limit::perMinute(6)->by($request->ip()));
    }
}
