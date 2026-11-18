<?php

declare(strict_types=1);

namespace App\Tests\Unit\TimeTracking;

use App\Common\Domain\Service\DateAndTimeService;
use App\TimeTracking\Domain\Exception\ItemIsDoneException;
use App\TimeTracking\Domain\Exception\ItemNotOnTheListException;
use App\TimeTracking\Domain\Model\TrackedItem;
use App\TimeTracking\Domain\Service\TimeTrackingDomainService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;

final class TimeTrackingDomainServiceTest extends TestCase
{
    private MockClock $clock;
    private StandInTodoListFacade $todoList;
    private InMemoryTimeEntryStore $store;
    private TimeTrackingDomainService $timeTracking;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-17 09:00:00');
        Clock::set($this->clock);

        $this->todoList = new StandInTodoListFacade();
        $this->todoList->add('a', 'Buy milk');
        $this->todoList->add('b', 'Call mum');

        $this->store        = new InMemoryTimeEntryStore();
        $this->timeTracking = new TimeTrackingDomainService($this->store, $this->todoList);
    }

    public function testItemsWithoutATimerHaveNoTimeSpentEvenWhenTheyAreOld(): void
    {
        $this->clock->sleep(3600);

        $items = $this->timeTracking->trackedItems();

        self::assertCount(2, $items);
        self::assertSame(0, $items[0]->secondsSpent);
        self::assertFalse($items[0]->isRunning());
    }

    public function testOnlyTheTimeBetweenStartAndStopCounts(): void
    {
        $this->timeTracking->startTimer('a');
        $this->clock->sleep(90);
        $this->timeTracking->stopTimer('a');
        $this->clock->sleep(500);

        self::assertSame(90, $this->item('a')->secondsSpent);
        self::assertNull($this->item('a')->runningSince);
    }

    public function testTheTimeOfARunningTimerCountsUpAndItsStartIsKnown(): void
    {
        $this->timeTracking->startTimer('a');
        $this->clock->sleep(5);
        self::assertSame(5, $this->item('a')->secondsSpent);

        $this->clock->sleep(3661);

        self::assertSame(3666, $this->item('a')->secondsSpent);
        self::assertEquals(
            DateAndTimeService::getDateTimeImmutable('2026-11-17 09:00:00'),
            $this->item('a')->runningSince
        );
    }

    public function testTheTimeIsTheSumOfAllPeriods(): void
    {
        $this->timeTracking->startTimer('a');
        $this->clock->sleep(10);
        $this->timeTracking->stopTimer('a');
        $this->clock->sleep(100);
        $this->timeTracking->startTimer('a');
        $this->clock->sleep(20);
        $this->timeTracking->stopTimer('a');

        self::assertSame(30, $this->item('a')->secondsSpent);
    }

    public function testStartingATimerStopsTheOneOnAnotherItem(): void
    {
        $this->timeTracking->startTimer('a');
        $this->clock->sleep(10);
        $this->timeTracking->startTimer('b');
        $this->clock->sleep(20);

        self::assertSame(10, $this->item('a')->secondsSpent);
        self::assertFalse($this->item('a')->isRunning());
        self::assertSame(20, $this->item('b')->secondsSpent);
        self::assertTrue($this->item('b')->isRunning());
    }

    public function testStartingARunningTimerAgainChangesNothing(): void
    {
        $this->timeTracking->startTimer('a');
        $this->clock->sleep(10);
        $this->timeTracking->startTimer('a');
        $this->clock->sleep(10);

        self::assertSame(20, $this->item('a')->secondsSpent);
        self::assertCount(1, $this->store->load());
    }

    public function testStoppingATimerThatDoesNotRunChangesNothing(): void
    {
        $this->timeTracking->stopTimer('a');

        self::assertSame([], $this->store->load());
    }

    public function testNoTimerStartsOnADoneItem(): void
    {
        $this->todoList->markDone('a');

        $this->expectException(ItemIsDoneException::class);
        $this->expectExceptionMessage('This item is done, so no timer can be started on it.');

        $this->timeTracking->startTimer('a');
    }

    public function testNoTimerStartsOnAnItemThatIsNotOnTheList(): void
    {
        $this->expectException(ItemNotOnTheListException::class);

        $this->timeTracking->startTimer('unknown');
    }

    public function testAFailedStartLeavesTheRunningTimerRunning(): void
    {
        $this->todoList->markDone('b');
        $this->timeTracking->startTimer('a');

        try {
            $this->timeTracking->startTimer('b');
            self::fail('A timer on a done item must be refused.');
        } catch (ItemIsDoneException) {
        }

        self::assertTrue($this->item('a')->isRunning());
    }

    public function testATimerIsStoppedAtTheGivenTime(): void
    {
        $this->timeTracking->startTimer('a');
        $this->clock->sleep(100);

        $this->timeTracking->stopTimerAt('a', DateAndTimeService::getDateTimeImmutable('-40 seconds'));
        $this->clock->sleep(100);

        self::assertSame(60, $this->item('a')->secondsSpent);
    }

    public function testTimeSpentIsKeptWhenTheItemLeavesTheList(): void
    {
        $this->timeTracking->startTimer('a');
        $this->clock->sleep(42);
        $this->timeTracking->stopTimer('a');

        $this->todoList->remove('a');

        self::assertCount(1, $this->timeTracking->trackedItems());
        self::assertCount(1, $this->store->load());
    }

    private function item(string $id): TrackedItem
    {
        foreach ($this->timeTracking->trackedItems() as $item) {
            if ($item->itemId === $id) {
                return $item;
            }
        }

        self::fail("No item {$id}");
    }
}
