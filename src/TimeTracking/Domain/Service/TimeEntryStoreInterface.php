<?php

declare(strict_types=1);

namespace App\TimeTracking\Domain\Service;

use App\TimeTracking\Domain\Model\TimeEntry;

/**
 * Keeps the periods the timers ran, also of items that are no longer on the todo list.
 */
interface TimeEntryStoreInterface
{
    /**
     * @return list<TimeEntry> in the order they were started
     */
    public function load(): array;

    /**
     * @param list<TimeEntry> $entries
     */
    public function store(array $entries): void;
}
