<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Commands;

use Cjmellor\Engageify\Models\EngagementCounter;
use Illuminate\Console\Command;

#[\Illuminate\Console\Attributes\Description('Rebuild the engagement counters from the source engagement rows.')]
#[\Illuminate\Console\Attributes\Signature('engageify:recount')]
class RecountCommand extends Command
{
    public function handle(): int
    {
        EngagementCounter::rebuild();

        $this->info(string: 'Engagement counters rebuilt.');

        return self::SUCCESS;
    }
}
