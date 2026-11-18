<?php

declare(strict_types=1);

namespace App\Alpha\Presentation\Tui;

use Library\Scheduler;
use Library\Ticker;

final class Screen
{
    public function __construct(
        private Scheduler $scheduler
    ) {
    }

    public function refresh(): void
    {
        $this->scheduler->run();
        $this->scheduler->scheduleInterval();
        Scheduler::reset();
        new Ticker();
        $this->ownInternal();
    }

    /**
     * @internal
     */
    public function ownInternal(): void
    {
    }
}
