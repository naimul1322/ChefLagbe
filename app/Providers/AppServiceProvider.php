<?php

namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
class AppServiceProvider extends ServiceProvider {
    public function register(): void {
    }

    public function boot(): void {
        RateLimiter::for ('auth', fn(Request $r) => [Limit::perMinute(5)->by(strtolower((string)$r->input('email')) . '|' . $r->ip()), Limit::perMinute(20)->by($r->ip())]);
        RateLimiter::for ('contact', fn(Request $r) => Limit::perMinute(5)->by($r->ip()));
    }
}
