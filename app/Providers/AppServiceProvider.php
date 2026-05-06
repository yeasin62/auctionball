<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

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
        Vite::prefetch(concurrency: 3);

        // Production safety guard — HARD STOP if production is misconfigured to
        // run on SQLite. SQLite cannot handle the concurrent writes a live
        // auction generates (3 writes per bid, multi-tenant load) and will
        // throw SQLITE_BUSY under load. Throwing here fails fast on the first
        // request after deploy so the issue is caught immediately, not on
        // auction night when bids start dropping.
        if ($this->app->environment('production') && config('database.default') === 'sqlite') {
            throw new RuntimeException(
                'AuctionBall cannot run in production with DB_CONNECTION=sqlite. '
                . 'Switch to MySQL/MariaDB or PostgreSQL — see deploy/README.md '
                . 'and deploy/mysql/setup.sql for the prepared setup.'
            );
        }
    }
}
