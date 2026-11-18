<?php

declare(strict_types=1);

namespace App\TimeTracking\Domain\Service;

use App\TimeTracking\Domain\Model\TimeRecord;

interface TimeRecordStoreInterface
{
    /**
     * @return list<TimeRecord>
     */
    public function load(): array;

    /**
     * @param list<TimeRecord> $records
     */
    public function store(array $records): void;
}
