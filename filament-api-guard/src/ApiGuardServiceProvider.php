<?php

declare(strict_types=1);

namespace Spiggle\ApiGuard;

use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Spiggle\ApiGuard\Commands\InstallCommand;
use Spiggle\ApiGuard\Commands\PruneCommand;
use Spiggle\ApiGuard\Http\CaptureState;
use Spiggle\ApiGuard\Http\Middleware\CaptureApiRequest;
use Spiggle\ApiGuard\Http\Middleware\EnforceApiGuard;
use Spiggle\ApiGuard\Livewire\LiveBar;
use Spiggle\ApiGuard\Services\GuardRuleEngine;
use Spiggle\ApiGuard\Services\Pruner;
use Spiggle\ApiGuard\Services\ReplayService;
use Spiggle\ApiGuard\Support\Access;
use Spiggle\ApiGuard\Support\Settings;

final class ApiGuardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/api-guard.php', 'api-guard');

        $this->app->singleton(Settings::class);
        $this->app->singleton(CaptureState::class);
        $this->app->singleton(GuardRuleEngine::class);
        $this->app->singleton(ReplayService::class);
        $this->app->singleton(Pruner::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'api-guard');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->registerMiddleware();
        $this->registerGuardMiddleware();
        $this->registerExceptionTracking();
        $this->registerFilamentAssets();
        $this->registerLiveBar();
        $this->registerCommands();
        $this->registerPublishing();
        $this->registerSchedule();
    }

    private function registerMiddleware(): void
    {
        if (! config('api-guard.register_global_middleware', true)) {
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

    private function registerGuardMiddleware(): void
    {
        Event::listen(RouteMatched::class, function (RouteMatched $event): void {
            $middleware = $event->route->gatherMiddleware();

            if (in_array(EnforceApiGuard::class, $middleware, true)) {
                return;
            }

            // Append after the route's auth middleware so user/IP rules see
            // the resolved identity. The capture middleware remains global.
            $event->route->middleware([EnforceApiGuard::class]);
            $event->route->computedMiddleware = null;
        });
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
            Css::make('api-guard', __DIR__ . '/../resources/dist/api-guard.css'),
            Js::make('api-guard', __DIR__ . '/../resources/dist/api-guard.js')->defer(),
        ], package: 'spiggle/filament-api-guard');
    }

    private function registerLiveBar(): void
    {
        if (! class_exists(FilamentView::class) || ! class_exists(Livewire::class)) {
            return;
        }

        Livewire::component('api-guard-live-bar', LiveBar::class);

        FilamentView::registerRenderHook(PanelsRenderHook::BODY_END, function (): string {
            if (! request()->is('*/api-guard/requests*') || ! Access::allowed()) {
                return '';
            }

            return Blade::render('<livewire:api-guard-live-bar />');
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
            __DIR__ . '/../config/api-guard.php' => config_path('api-guard.php'),
        ], 'api-guard-config');

        $this->publishes([
            __DIR__ . '/../database/migrations/2026_01_01_000000_create_api_guard_tables.php' => database_path('migrations/2026_01_01_000000_create_api_guard_tables.php'),
            __DIR__ . '/../database/migrations/2026_01_02_000000_add_api_guard_rules.php' => database_path('migrations/2026_01_02_000000_add_api_guard_rules.php'),
        ], 'api-guard-migrations');

        $this->publishes([
            __DIR__ . '/../resources/dist' => public_path('vendor/api-guard'),
        ], 'api-guard-assets');
    }

    private function registerSchedule(): void
    {
        if (! config('api-guard.schedule_prune', true)) {
            return;
        }

        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command('api-guard:prune')
                ->dailyAt('03:15')
                ->withoutOverlapping();
        });
    }
}
