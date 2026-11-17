<?php

declare(strict_types=1);

namespace App\Tests\Unit\TimeTracking;

use App\Tests\Unit\Todo\InMemoryTodoItemStore;
use App\TimeTracking\Domain\Service\TimeTrackingDomainService;
use App\TimeTracking\Infrastructure\Adapter\StopsTimersOfTodoItems;
use App\Todo\Domain\Service\TodoListDomainService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;

/**
 * The todo list and the time tracking wired as in the application, on a clock the test moves.
 */
abstract class TimeTrackingTestCase extends TestCase
{
    protected MockClock $clock;
    protected TodoListDomainService $todoList;
    protected TimeTrackingDomainService $timeTracking;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-17 09:00:00.000000');
        Clock::set($this->clock);

        $periods            = new InMemoryTimePeriodStore();
        $this->todoList     = new TodoListDomainService(new InMemoryTodoItemStore(), new StopsTimersOfTodoItems($periods));
        $this->timeTracking = new TimeTrackingDomainService($periods, $this->todoList);
    }

    protected function spent(string $itemId): int
    {
        foreach ($this->timeTracking->trackedItems() as $row) {
            if ($row->item->id === $itemId) {
                return $row->spentSeconds;
            }
        }

        self::fail('Item not tracked');
    }

    protected function isRunning(string $itemId): bool
    {
        foreach ($this->timeTracking->trackedItems() as $row) {
            if ($row->item->id === $itemId) {
                return $row->isRunning();
            }
        }

        return false;
    }
}
