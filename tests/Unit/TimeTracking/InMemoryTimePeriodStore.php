<?php

declare(strict_types=1);

namespace App\Tests\Unit\TimeTracking;

use App\TimeTracking\Domain\Service\TimePeriodStoreInterface;

final class InMemoryTimePeriodStore implements TimePeriodStoreInterface
{
    /** @var list<\App\TimeTracking\Domain\Model\TimePeriod> */
    private array $periods = [];

    public function load(): array
    {
        return $this->periods;
    }

    public function store(array $periods): void
    {
        $this->periods = $periods;
    }
}
