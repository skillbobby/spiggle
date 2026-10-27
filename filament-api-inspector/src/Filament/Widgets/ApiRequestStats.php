<?php

declare(strict_types=1);

namespace Spiggle\ApiInspector\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Spiggle\ApiInspector\Models\ApiReplay;
use Spiggle\ApiInspector\Models\ApiRequest;
use Spiggle\ApiInspector\Support\Settings;
use Throwable;

final class ApiRequestStats extends StatsOverviewWidget
{
    protected function getPollingInterval(): ?string
    {
        $settings = app(Settings::class);

        return $settings->liveEnabled() ? $settings->liveInterval() . 's' : null;
    }

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        try {
            $from = now()->subDay();

            $total = ApiRequest::query()->where('captured_at', '>=', $from)->count();
            $errors = ApiRequest::query()->where('captured_at', '>=', $from)->where('status_code', '>=', 500)->count();
            $average = (float) ApiRequest::query()->where('captured_at', '>=', $from)->avg('duration_ms');
            $replays = ApiReplay::query()->where('created_at', '>=', $from)->count();
        } catch (Throwable) {
            $total = $errors = $replays = 0;
            $average = 0.0;
        }

        return [
            Stat::make('Requests', number_format($total))
                ->description('Last 24 hours')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('primary'),

            Stat::make('Server errors', number_format($errors))
                ->description($errors > 0 ? 'Needs attention' : 'All clear')
                ->descriptionIcon($errors > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($errors > 0 ? 'danger' : 'success'),

            Stat::make('Average latency', $average > 0 ? number_format($average, $average < 10 ? 2 : 1) . ' ms' : '—')
                ->description($average >= 500 ? 'Slow responses detected' : 'Across captured requests')
                ->descriptionIcon('heroicon-m-bolt')
                ->color($average >= 500 ? 'warning' : 'gray'),

            Stat::make('Replays', number_format($replays))
                ->description('Simulated and live')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('gray'),
        ];
    }
}
