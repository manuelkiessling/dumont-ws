<?php

declare(strict_types=1);

namespace App\TimeTracking\Domain\Service;

use App\TimeTracking\Domain\Model\TimeEntry;

interface TimeEntryStoreInterface
{
    /** @return list<TimeEntry> */
    public function load(): array;

    /** @param list<TimeEntry> $entries */
    public function store(array $entries): void;
}
