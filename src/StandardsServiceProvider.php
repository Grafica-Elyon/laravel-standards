<?php

namespace Elyon\LaravelStandards;

use Elyon\LaravelStandards\Commands\ExportCommand;
use Elyon\LaravelStandards\Commands\InstallCommand;
use Illuminate\Support\ServiceProvider;

class StandardsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/elyon-standards.php', 'elyon-standards');
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            InstallCommand::class,
            ExportCommand::class,
        ]);

        $this->publishes([
            __DIR__.'/../stubs/pint.json' => base_path('pint.json'),
            __DIR__.'/../stubs/phpstan.neon' => base_path('phpstan.neon'),
            __DIR__.'/../stubs/hooks/pre-commit' => base_path('.githooks/pre-commit'),
        ], 'standards-config');

        $this->publishes([
            __DIR__.'/../config/elyon-standards.php' => config_path('elyon-standards.php'),
        ], 'elyon-standards-config');
    }
}
