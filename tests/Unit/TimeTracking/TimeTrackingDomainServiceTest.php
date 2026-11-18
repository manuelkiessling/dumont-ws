<?php

declare(strict_types=1);

namespace App\Tests\Unit\TimeTracking;

use App\Tests\Unit\Todo\InMemoryTodoItemStore;
use App\TimeTracking\Domain\Exception\ItemIsDoneException;
use App\TimeTracking\Domain\Exception\ItemNotTrackableException;
use App\TimeTracking\Domain\Service\TimeTrackingDomainService;
use App\Todo\Domain\Service\TodoListDomainService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class TimeTrackingDomainServiceTest extends TestCase
{
    use ClockSensitiveTrait;

    private TodoListDomainService $todoList;
    private TimeTrackingDomainService $timeTracking;

    protected function setUp(): void
    {
        self::mockTime('2026-11-18 10:00:00.700');

        $todoItems          = new InMemoryTodoItemStore();
        $this->timeTracking = new TimeTrackingDomainService(new InMemoryTimeRecordStore(), $todoItems);
        $this->todoList     = new TodoListDomainService($todoItems, $this->timeTracking);
    }

    public function testAnItemWithoutATimerHasNoTime(): void
    {
        $item = $this->todoList->addItem('Buy milk');
        self::mockTime('2026-11-18 12:00:00');

        self::assertSame(0, $this->timeTracking->items()[0]->secondsSpent);
        self::assertNull($this->timeTracking->items()[0]->runningSince);
        self::assertSame(0, $this->timeTracking->secondsSpentOn($item->id));
    }

    public function testOnlyTheTimeBetweenStartAndStopCounts(): void
    {
        $item = $this->todoList->addItem('Buy milk');
        self::mockTime('2026-11-18 10:05:00');
        $this->timeTracking->startTimer($item->id);
        self::mockTime('2026-11-18 11:06:07');
        $this->timeTracking->stopTimer();
        self::mockTime('2026-11-18 15:00:00');

        self::assertSame(3600 + 60 + 7, $this->timeTracking->secondsSpentOn($item->id));
    }

    public function testTheTimeCountsUpWhileTheTimerRunsAndStartIsToTheSecond(): void
    {
        $item = $this->todoList->addItem('Buy milk');
        self::mockTime('2026-11-18 10:05:00.900');
        $this->timeTracking->startTimer($item->id);

        self::mockTime('2026-11-18 10:05:42.100');

        $tracked = $this->timeTracking->items()[0];
        self::assertSame(42, $tracked->secondsSpent);
        self::assertSame('2026-11-18 10:05:00.000000', $tracked->runningSince?->format('Y-m-d H:i:s.u'));
    }

    public function testTheTimesOfSeveralPeriodsAreAdded(): void
    {
        $item = $this->todoList->addItem('Buy milk');
        $this->timeTracking->startTimer($item->id);
        self::mockTime('2026-11-18 10:00:10.700');
        $this->timeTracking->stopTimer();
        self::mockTime('2026-11-18 10:30:00.700');
        $this->timeTracking->startTimer($item->id);
        self::mockTime('2026-11-18 10:30:05.700');
        $this->timeTracking->stopTimer();

        self::assertSame(15, $this->timeTracking->secondsSpentOn($item->id));
    }

    public function testStartingATimerStopsTheTimerOnAnotherItem(): void
    {
        $first  = $this->todoList->addItem('First');
        $second = $this->todoList->addItem('Second');
        $this->timeTracking->startTimer($first->id);
        self::mockTime('2026-11-18 10:00:10.700');
        $this->timeTracking->startTimer($second->id);
        self::mockTime('2026-11-18 10:00:30.700');

        [$firstTracked, $secondTracked] = $this->timeTracking->items();
        self::assertFalse($firstTracked->isRunning());
        self::assertSame(10, $firstTracked->secondsSpent);
        self::assertTrue($secondTracked->isRunning());
        self::assertSame(20, $secondTracked->secondsSpent);
    }

    public function testStartingARunningTimerAgainChangesNothing(): void
    {
        $item = $this->todoList->addItem('Buy milk');
        $this->timeTracking->startTimer($item->id);
        self::mockTime('2026-11-18 10:00:10.700');
        $this->timeTracking->startTimer($item->id);
        self::mockTime('2026-11-18 10:00:20.700');

        self::assertSame(20, $this->timeTracking->secondsSpentOn($item->id));
    }

    public function testMarkingTheItemDoneStopsItsTimer(): void
    {
        $item = $this->todoList->addItem('Buy milk');
        $this->timeTracking->startTimer($item->id);
        self::mockTime('2026-11-18 10:00:10.700');
        $this->todoList->markItemDone($item->id);
        self::mockTime('2026-11-18 11:00:00');

        self::assertFalse($this->timeTracking->items()[0]->isRunning());
        self::assertSame(10, $this->timeTracking->secondsSpentOn($item->id));
    }

    public function testDeletingTheItemStopsItsTimerAndKeepsItsTime(): void
    {
        $item = $this->todoList->addItem('Buy milk');
        $this->timeTracking->startTimer($item->id);
        self::mockTime('2026-11-18 10:00:10.700');
        $this->todoList->deleteItem($item->id);
        self::mockTime('2026-11-18 11:00:00');

        self::assertSame([], $this->timeTracking->items());
        self::assertSame(10, $this->timeTracking->secondsSpentOn($item->id));
    }

    public function testMarkingAnotherItemDoneLeavesTheRunningTimerAlone(): void
    {
        $running = $this->todoList->addItem('Running');
        $other   = $this->todoList->addItem('Other');
        $this->timeTracking->startTimer($running->id);

        $this->todoList->markItemDone($other->id);
        $this->todoList->deleteItem($other->id);

        self::assertTrue($this->timeTracking->items()[0]->isRunning());
    }

    public function testNoTimerCanBeStartedOnADoneItem(): void
    {
        $item = $this->todoList->addItem('Buy milk');
        $this->todoList->markItemDone($item->id);

        $this->expectException(ItemIsDoneException::class);

        $this->timeTracking->startTimer($item->id);
    }

    public function testNoTimerCanBeStartedOnAnItemNotOnTheList(): void
    {
        $this->expectException(ItemNotTrackableException::class);

        $this->timeTracking->startTimer('unknown');
    }

    public function testStoppingWithoutARunningTimerChangesNothing(): void
    {
        $item = $this->todoList->addItem('Buy milk');

        $this->timeTracking->stopTimer();

        self::assertSame(0, $this->timeTracking->secondsSpentOn($item->id));
    }
}
