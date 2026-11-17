<?php

declare(strict_types=1);

namespace App\Tests\Unit\TimeTracking;

use App\TimeTracking\Domain\Model\ItemTime;
use App\TimeTracking\Domain\Service\TimeRecordStoreInterface;

final class InMemoryTimeRecordStore implements TimeRecordStoreInterface
{
    /** @var list<ItemTime> */
    private array $times = [];

    public function load(): array
    {
        return $this->times;
    }

    public function store(array $times): void
    {
        $this->times = $times;
    }
}
