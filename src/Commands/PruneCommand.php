<?php

declare(strict_types=1);

namespace Spiggle\ApiGuard\Commands;

use Illuminate\Console\Command;
use Spiggle\ApiGuard\Services\Pruner;

final class PruneCommand extends Command
{
    protected $signature = 'api-guard:prune
        {--days= : Delete records older than this many days}
        {--max= : Keep at most this many records}
        {--dry-run : Report what would be deleted without deleting}';

    protected $description = 'Prune tracked API Guard requests and replays.';

    public function handle(Pruner $pruner): int
    {
        $days = $this->option('days') !== null ? max(0, (int) $this->option('days')) : null;
        $max = $this->option('max') !== null ? max(0, (int) $this->option('max')) : null;
        $dryRun = (bool) $this->option('dry-run');

        $result = $pruner->prune($days, $max, $dryRun);

        $this->components->info(sprintf(
            '%s %d record(s): %d expired, %d over the limit.',
            $dryRun ? 'Would prune' : 'Pruned',
            $result['total'],
            $result['expired'],
            $result['overflow'],
        ));

        return self::SUCCESS;
    }
}
