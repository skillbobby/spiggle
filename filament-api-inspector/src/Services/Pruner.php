<?php

declare(strict_types=1);

namespace Spiggle\ApiInspector\Services;

use Spiggle\ApiInspector\Models\ApiReplay;
use Spiggle\ApiInspector\Models\ApiRequest;
use Spiggle\ApiInspector\Support\Settings;

final class Pruner
{
    public function __construct(private readonly Settings $settings)
    {
    }

    /**
     * @return array{expired: int, overflow: int, total: int}
     */
    public function prune(?int $days = null, ?int $maxRecords = null, bool $dryRun = false): array
    {
        $days ??= $this->settings->retentionDays();
        $maxRecords ??= $this->settings->maxRecords();

        $expired = 0;
        $overflow = 0;

        if ($days > 0) {
            $query = ApiRequest::query()->where('captured_at', '<', now()->subDays($days));
            $expired = $dryRun ? $query->count() : $this->deleteRequests($query->pluck('id')->all());
        }

        if ($maxRecords > 0) {
            $total = ApiRequest::query()->count();

            if ($total > $maxRecords) {
                $ids = ApiRequest::query()
                    ->orderBy('captured_at')
                    ->orderBy('id')
                    ->limit($total - $maxRecords)
                    ->pluck('id')
                    ->all();

                $overflow = $dryRun ? count($ids) : $this->deleteRequests($ids);
            }
        }

        return [
            'expired' => $expired,
            'overflow' => $overflow,
            'total' => $expired + $overflow,
        ];
    }

    /**
     * @param  array<int, string>  $ids
     */
    private function deleteRequests(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        $deleted = 0;

        foreach (array_chunk($ids, 500) as $chunk) {
            ApiReplay::query()->whereIn('api_request_id', $chunk)->delete();
            $deleted += ApiRequest::query()->whereIn('id', $chunk)->delete();
        }

        return $deleted;
    }
}
