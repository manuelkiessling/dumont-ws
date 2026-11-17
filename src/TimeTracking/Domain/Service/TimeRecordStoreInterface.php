<?php

declare(strict_types=1);

namespace App\TimeTracking\Domain\Service;

use App\TimeTracking\Domain\Model\ItemTime;

/**
 * Keeps the time spent on items.
 */
interface TimeRecordStoreInterface
{
    /**
     * @return list<ItemTime>
     */
    public function load(): array;

    /**
     * @param list<ItemTime> $times
     */
    public function store(array $times): void;
}
