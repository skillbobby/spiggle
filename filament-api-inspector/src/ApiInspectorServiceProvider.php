<?php

declare(strict_types=1);

namespace Spiggle\ApiInspector;

use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Spiggle\ApiInspector\Commands\InstallCommand;
use Spiggle\ApiInspector\Commands\PruneCommand;
use Spiggle\ApiInspector\Http\CaptureState;
use Spiggle\ApiInspector\Http\Middleware\CaptureApiRequest;
use Spiggle\ApiInspector\Livewire\LiveBar;
use Spiggle\ApiInspector\Services\Pruner;
use Spiggle\ApiInspector\Services\ReplayService;
use Spiggle\ApiInspector\Support\Access;
use Spiggle\ApiInspector\Support\Settings;

final class ApiInspectorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/api-inspector.php', 'api-inspector');

        $this->app->singleton(Settings::class);
        $this->app->singleton(CaptureState::class);
        $this->app->singleton(ReplayService::class);
        $this->app->singleton(Pruner::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'api-inspector');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->registerMiddleware();
        $this->registerExceptionTracking();
        $this->registerFilamentAssets();
        $this->registerLiveBar();
        $this->registerCommands();
        $this->registerPublishing();
        $this->registerSchedule();
    }

    private function registerMiddleware(): void
    {
        if (! config('api-inspector.register_global_middleware', true)) {
            return;
        }

        try {
            $kernel = $this->app->make(HttpKernel::class);

            if (method_exists($kernel, 'pushMiddleware')) {
                $kernel->pushMiddleware(CaptureApiRequest::class);
            }
        } catch (\Throwable) {
            // Console contexts may not have a usable HTTP kernel.
        }
    }

    private function registerExceptionTracking(): void
    {
        $callback = function (ExceptionHandler $handler): void {
            if (! method_exists($handler, 'reportable')) {
                return;
            }

            $handler->reportable(function (\Throwable $exception): void {
                try {
                    app(CaptureState::class)->recordException(request(), $exception);
                } catch (\Throwable) {
                    // No active request (console) — nothing to attach.
                }
            });
        };

        if ($this->app->resolved(ExceptionHandler::class)) {
            $callback($this->app->make(ExceptionHandler::class));

            return;
        }

        $this->app->afterResolving(ExceptionHandler::class, $callback);
    }

    private function registerFilamentAssets(): void
    {
        if (! class_exists(FilamentAsset::class)) {
            return;
        }

        FilamentAsset::register([
            Css::make('api-inspector', __DIR__ . '/../resources/dist/api-inspector.css'),
            Js::make('api-inspector', __DIR__ . '/../resources/dist/api-inspector.js')->defer(),
        ], package: 'spiggle/filament-api-inspector');
    }

    private function registerLiveBar(): void
    {
        if (! class_exists(FilamentView::class) || ! class_exists(Livewire::class)) {
            return;
        }

        Livewire::component('api-inspector-live-bar', LiveBar::class);

        FilamentView::registerRenderHook(PanelsRenderHook::BODY_END, function (): string {
            $routeName = (string) request()->route()?->getName();

            if (
                $routeName === ''
                || (! str_contains($routeName, 'api-requests') && ! str_contains($routeName, 'api-inspector'))
                || ! Access::allowed()
            ) {
                return '';
            }

            return Blade::render('<livewire:api-inspector-live-bar />');
        });
    }

    private function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            PruneCommand::class,
            InstallCommand::class,
        ]);
    }

    private function registerPublishing(): void
    {
        $this->publishes([
            __DIR__ . '/../config/api-inspector.php' => config_path('api-inspector.php'),
        ], 'api-inspector-config');

        $this->publishes([
            __DIR__ . '/../database/migrations/2026_01_01_000000_create_api_inspector_tables.php' => database_path('migrations/2026_01_01_000000_create_api_inspector_tables.php'),
        ], 'api-inspector-migrations');

        $this->publishes([
            __DIR__ . '/../resources/dist' => public_path('vendor/api-inspector'),
        ], 'api-inspector-assets');
    }

    private function registerSchedule(): void
    {
        if (! config('api-inspector.schedule_prune', true)) {
            return;
        }

        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command('api-inspector:prune')
                ->dailyAt('03:15')
                ->withoutOverlapping();
        });
    }
}
