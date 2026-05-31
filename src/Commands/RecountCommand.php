<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Commands;

use Cjmellor\Engageify\Models\EngagementCounter;
use Illuminate\Console\Command;

class RecountCommand extends Command
{
    protected $signature = 'engageify:recount';

    protected $description = 'Rebuild the engagement counters from the source engagement rows.';

    public function handle(): int
    {
        EngagementCounter::rebuild();

        $this->info(string: 'Engagement counters rebuilt.');

        return self::SUCCESS;
    }
}
