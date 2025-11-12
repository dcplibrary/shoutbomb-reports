<?php

namespace Dcplibrary\ShoutbombFailureReports\Providers;

use Dcplibrary\ShoutbombFailureReports\Commands\CheckReportsCommand;
use Dcplibrary\ShoutbombFailureReports\Services\GraphApiService;
use Illuminate\Support\ServiceProvider;

class ShoutbombFailureReportsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/shoutbomb-reports.php',
            'shoutbomb-reports'
        );

        // Register GraphApiService as singleton
        $this->app->singleton(GraphApiService::class, function ($app) {
            return new GraphApiService(
                config('shoutbomb-reports.graph')
            );
        });

        // IMPORTANT: Register Artisan commands outside runningInConsole so web Artisan::call() can find them
        $this->commands([
            CheckReportsCommand::class,
        ]);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Only publishes should be in runningInConsole
        if ($this->app->runningInConsole()) {
            // Publish config
            $this->publishes([
                __DIR__ . '/../../config/shoutbomb-reports.php' => config_path('shoutbomb-reports.php'),
            ], 'shoutbomb-reports-config');

            // Publish migrations
            $this->publishes([
                __DIR__ . '/../database/migrations/' => database_path('migrations'),
            ], 'shoutbomb-reports-migrations');
        }

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
