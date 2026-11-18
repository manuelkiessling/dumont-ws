<?php

declare(strict_types=1);

namespace App\Tests\Unit\TimeTracking;

use App\TimeTracking\Domain\Service\TimeRecordStoreInterface;

final class InMemoryTimeRecordStore implements TimeRecordStoreInterface
{
    private array $records = [];

    public function load(): array
    {
        return $this->records;
    }

    public function store(array $records): void
    {
        $this->records = $records;
    }
}
