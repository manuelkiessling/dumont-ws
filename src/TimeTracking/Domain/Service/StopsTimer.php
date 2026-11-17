<?php

declare(strict_types=1);

namespace App\TimeTracking\Domain\Service;

use App\Common\Domain\Service\DateAndTimeService;

/**
 * Stops the running timer of an item, which needs the periods only.
 */
final readonly class StopsTimer
{
    public function __construct(
        private TimePeriodStoreInterface $periods
    ) {
    }

    public function stop(string $itemId): void
    {
        $now     = DateAndTimeService::getDateTimeImmutable();
        $periods = $this->periods->load();
        $changed = false;

        foreach ($periods as $index => $period) {
            if ($period->itemId === $itemId && $period->isRunning()) {
                $periods[$index] = $period->stoppedAt($now);
                $changed         = true;
            }
        }

        if ($changed) {
            $this->periods->store($periods);
        }
    }
}
