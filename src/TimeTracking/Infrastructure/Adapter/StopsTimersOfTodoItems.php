<?php

declare(strict_types=1);

namespace App\TimeTracking\Infrastructure\Adapter;

use App\TimeTracking\Domain\Service\StopsTimer;
use App\TimeTracking\Domain\Service\TimePeriodStoreInterface;
use App\Todo\Domain\Service\ItemTimersInterface;

/**
 * Lets the todo list stop an item's timer without the todo list knowing the time tracking.
 */
final readonly class StopsTimersOfTodoItems implements ItemTimersInterface
{
    public function __construct(
        private TimePeriodStoreInterface $periods
    ) {
    }

    public function stopTimerOf(string $itemId): void
    {
        new StopsTimer($this->periods)->stop($itemId);
    }
}
