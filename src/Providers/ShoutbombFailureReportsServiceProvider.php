<?php

namespace Dcplibrary\ShoutbombFailureReports\Providers;

use Dcplibrary\ShoutbombFailureReports\Commands\CheckFailureReportsCommand;
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
            __DIR__ . '/../../config/shoutbomb-failure-reports.php',
            'shoutbomb-failure-reports'
        );

        // Register GraphApiService as singleton
        $this->app->singleton(GraphApiService::class, function ($app) {
            return new GraphApiService(
                config('shoutbomb-failure-reports.graph')
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../../config/shoutbomb-failure-reports.php' => config_path('shoutbomb-failure-reports.php'),
        ], 'shoutbomb-failure-reports-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'shoutbomb-failure-reports-migrations');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                CheckFailureReportsCommand::class,
            ]);
        }
    }
}
