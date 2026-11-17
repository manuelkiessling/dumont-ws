<?php

declare(strict_types=1);

namespace App\TimeTracking\Domain\Service;

use App\TimeTracking\Domain\Model\TimePeriod;

/**
 * Keeps the periods the timers ran, also those of items that are no longer on the list.
 */
interface TimePeriodStoreInterface
{
    /**
     * @return list<TimePeriod> in the order they were started
     */
    public function load(): array;

    /**
     * @param list<TimePeriod> $periods
     */
    public function store(array $periods): void;
}
