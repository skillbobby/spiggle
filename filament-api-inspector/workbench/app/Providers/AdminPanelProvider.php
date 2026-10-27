<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Spiggle\ApiInspector\ApiInspectorPlugin;
use Workbench\App\Http\Middleware\AutoLogin;
use Workbench\App\Models\User;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        config(['auth.providers.users.model' => User::class]);

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('Spiggle API Inspector')
            ->colors(['primary' => Color::Indigo])
            ->authGuard('web')
            ->plugin(ApiInspectorPlugin::make())
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                AutoLogin::class,
            ])
            ->authMiddleware([]);
    }
}
