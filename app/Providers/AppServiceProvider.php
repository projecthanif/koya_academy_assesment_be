<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void {}

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('bookings', function (Request $request): Limit {
            return Limit::perMinute(10)
                ->by('booking:ip:'.$request->ip())
                ->response(fn (Request $request, array $headers) => response()->json([
                    'error' => 'Too many booking attempts.',
                ], 429, $headers));
        });
    }
}
