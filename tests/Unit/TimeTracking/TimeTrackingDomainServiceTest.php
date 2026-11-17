<?php

declare(strict_types=1);

namespace App\Tests\Unit\TimeTracking;

use App\Tests\Unit\Todo\InMemoryTodoItemStore;
use App\TimeTracking\Domain\Exception\TimeTrackingRefusedException;
use App\TimeTracking\Domain\Model\TrackedItem;
use App\TimeTracking\Domain\Service\TimeTrackingDomainService;
use App\Todo\Domain\Service\TodoListDomainService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;

final class TimeTrackingDomainServiceTest extends TestCase
{
    private MockClock $clock;
    private InMemoryTimeEntryStore $entries;
    private TimeTrackingDomainService $timeTracking;
    private TodoListDomainService $todoList;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-17 09:00:00.700');
        Clock::set($this->clock);

        $items              = new InMemoryTodoItemStore();
        $this->entries      = new InMemoryTimeEntryStore();
        $this->timeTracking = new TimeTrackingDomainService($this->entries, $items);
        $this->todoList     = new TodoListDomainService($items, $this->timeTracking);
    }

    public function testItemsStartWithoutTimeAndEvenAnOldItemHasNoneUntilItsTimerRuns(): void
    {
        $this->todoList->addItem('Buy milk');
        $this->clock->sleep(3600);

        $tracked = $this->timeTracking->trackedItems();

        self::assertCount(1, $tracked);
        self::assertSame(0, $tracked[0]->seconds);
        self::assertFalse($tracked[0]->isRunning());
    }

    public function testTheTimeIsTheSumOfAllPeriodsTheTimerRan(): void
    {
        $id = $this->todoList->addItem('Buy milk')->id;

        $this->timeTracking->startTimer($id);
        $this->clock->sleep(65);
        $this->timeTracking->stopTimerOf($id);
        $this->clock->sleep(1000);
        $this->timeTracking->startTimer($id);
        $this->clock->sleep(10);

        $item = $this->only();
        self::assertSame(75, $item->seconds);
        self::assertTrue($item->isRunning());
        self::assertSame('2026-11-17 09:17:45', $item->runningSince?->format('Y-m-d H:i:s'));

        $this->timeTracking->stopTimerOf($id);
        $this->clock->sleep(500);
        self::assertSame(75, $this->only()->seconds);
        self::assertFalse($this->only()->isRunning());
    }

    public function testStartingATimerStopsTheTimerOnAnotherItem(): void
    {
        $first  = $this->todoList->addItem('First')->id;
        $second = $this->todoList->addItem('Second')->id;

        $this->timeTracking->startTimer($first);
        $this->clock->sleep(30);
        $this->timeTracking->startTimer($second);
        $this->clock->sleep(20);

        [$a, $b] = $this->timeTracking->trackedItems();
        self::assertSame(30, $a->seconds);
        self::assertFalse($a->isRunning());
        self::assertSame(20, $b->seconds);
        self::assertTrue($b->isRunning());
    }

    public function testStartingARunningTimerAgainChangesNothing(): void
    {
        $id = $this->todoList->addItem('Buy milk')->id;

        $this->timeTracking->startTimer($id);
        $this->clock->sleep(5);
        $this->timeTracking->startTimer($id);
        $this->clock->sleep(5);

        self::assertSame(10, $this->only()->seconds);
        self::assertCount(1, $this->entries->load());
    }

    public function testNoTimerCanBeStartedOnADoneItem(): void
    {
        $id = $this->todoList->addItem('Buy milk')->id;
        $this->todoList->markItemDone($id);

        $this->expectException(TimeTrackingRefusedException::class);
        $this->expectExceptionMessage('done');
        $this->timeTracking->startTimer($id);
    }

    public function testNoTimerCanBeStartedOnAnItemNotOnTheList(): void
    {
        $this->expectException(TimeTrackingRefusedException::class);
        $this->timeTracking->startTimer('unknown');
    }

    public function testMarkingTheItemDoneStopsItsTimerAndKeepsTheTime(): void
    {
        $id = $this->todoList->addItem('Buy milk')->id;
        $this->timeTracking->startTimer($id);
        $this->clock->sleep(42);

        $this->todoList->markItemDone($id);
        $this->clock->sleep(100);

        $item = $this->only();
        self::assertFalse($item->isRunning());
        self::assertTrue($item->isDone);
        self::assertSame(42, $item->seconds);
    }

    public function testDeletingTheItemStopsItsTimerAndTheTimeIsKept(): void
    {
        $id = $this->todoList->addItem('Buy milk')->id;
        $this->timeTracking->startTimer($id);
        $this->clock->sleep(42);

        $this->todoList->deleteItem($id);
        $this->clock->sleep(100);

        $item = $this->only();
        self::assertSame('Buy milk', $item->text);
        self::assertTrue($item->isDeleted);
        self::assertFalse($item->isRunning());
        self::assertSame(42, $item->seconds);
    }

    public function testDeletingAnItemDoesNotStopTheTimerOnAnotherItem(): void
    {
        $first  = $this->todoList->addItem('First')->id;
        $second = $this->todoList->addItem('Second')->id;
        $this->timeTracking->startTimer($second);

        $this->todoList->deleteItem($first);

        self::assertTrue($this->only()->isRunning());
    }

    private function only(): TrackedItem
    {
        $tracked = $this->timeTracking->trackedItems();
        self::assertCount(1, $tracked);

        return $tracked[0];
    }
}
