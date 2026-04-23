<?php

declare(strict_types=1);

namespace Innonazarene\PrismInit;

use Illuminate\Support\ServiceProvider;
use Innonazarene\PrismInit\Commands\PrismInitCommand;

class PrismServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([PrismInitCommand::class]);

            $this->publishes([
                __DIR__ . '/../config/prism-init.php' => config_path('prism-init.php'),
            ], 'prism-init-config');

            $this->publishes([
                __DIR__ . '/../stubs' => base_path('stubs/prism-init'),
            ], 'prism-init-stubs');
        }
    }

    public function register(): void
    {
        if (file_exists($config = __DIR__ . '/../config/prism-init.php')) {
            $this->mergeConfigFrom($config, 'prism-init');
        }
    }
}
