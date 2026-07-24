<?php

namespace App\Console\Commands;

use App\Services\OpenPlayService;
use Illuminate\Console\Command;

class AutoEndStaleOpenPlaySessions extends Command
{
    protected $signature = 'open-play:auto-end-stale {--hours=12 : Hours of inactivity before an in-progress session is force-ended}';

    protected $description = 'End Open Play sessions the host forgot to close - any in_progress room with no activity for the configured window';

    public function handle(OpenPlayService $openPlay): int
    {
        $hours = (int) $this->option('hours');

        $count = $openPlay->autoEndStaleSessions($hours);

        $this->info("Auto-ended {$count} stale Open Play session(s) (no activity for {$hours}+ hours).");

        return self::SUCCESS;
    }
}
