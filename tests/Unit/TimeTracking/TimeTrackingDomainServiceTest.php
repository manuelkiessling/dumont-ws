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
    private FakeTodoListFacade $todoList;
    private InMemoryTimePeriodStore $store;
    private TimeTrackingDomainService $timeTracking;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-17 09:00:00.750');
        Clock::set($this->clock);

        $this->todoList     = new FakeTodoListFacade();
        $this->store        = new InMemoryTimePeriodStore();
        $this->timeTracking = new TimeTrackingDomainService($this->store, $this->todoList);

        $this->todoList->add('a', 'Write report');
        $this->todoList->add('b', 'Call mum');
    }

    public function testItemsWithoutTimersHaveNoTime(): void
    {
        $items = $this->timeTracking->trackedItems();

        self::assertSame(
            ['Write report', 'Call mum'],
            array_map(static fn (TrackedItem $i): string => $i->text, $items)
        );
        self::assertSame([0, 0], array_map(static fn (TrackedItem $i): int => $i->seconds, $items));
        self::assertFalse($items[0]->isRunning());
    }

    public function testOnlyTheTimeBetweenStartAndStopCounts(): void
    {
        $this->clock->sleep(3600);
        $this->timeTracking->startTimer('a');
        $this->clock->sleep(65);
        $this->timeTracking->stopRunningTimer();
        $this->clock->sleep(500);

        self::assertSame(65, $this->seconds('a'));
        self::assertSame(0, $this->seconds('b'));
    }

    public function testTheTimeOfARunningTimerCountsUp(): void
    {
        $this->timeTracking->startTimer('a');
        $this->clock->sleep(10);
        self::assertSame(10, $this->seconds('a'));

        $this->clock->sleep(1);
        self::assertSame(11, $this->seconds('a'));
    }

    public function testARunningTimerKnowsWhenItWasStartedToTheSecond(): void
    {
        $this->timeTracking->startTimer('a');

        $item = $this->timeTracking->trackedItems()[0];
        self::assertTrue($item->isRunning());
        self::assertEquals(DateAndTimeService::getDateTimeImmutable('2026-11-17 09:00:00'), $item->runningSince);
    }

    public function testTheTimeIsTheSumOfAllPeriods(): void
    {
        $this->timeTracking->startTimer('a');
        $this->clock->sleep(30);
        $this->timeTracking->stopRunningTimer();
        $this->clock->sleep(1000);
        $this->timeTracking->startTimer('a');
        $this->clock->sleep(3700);
        $this->timeTracking->stopRunningTimer();

        self::assertSame(3730, $this->seconds('a'));
    }

    public function testStartingATimerStopsTheOneOnAnotherItem(): void
    {
        $this->timeTracking->startTimer('a');
        $this->clock->sleep(20);
        $this->timeTracking->startTimer('b');
        $this->clock->sleep(5);

        $items = $this->timeTracking->trackedItems();
        self::assertFalse($items[0]->isRunning());
        self::assertSame(20, $items[0]->seconds);
        self::assertTrue($items[1]->isRunning());
        self::assertSame(5, $items[1]->seconds);
    }

    public function testStartingTheTimerThatRunsChangesNothing(): void
    {
        $this->timeTracking->startTimer('a');
        $this->clock->sleep(20);
        $this->timeTracking->startTimer('a');

        self::assertSame(20, $this->seconds('a'));
        self::assertCount(1, $this->store->load());
    }

    public function testStoppingWithoutARunningTimerChangesNothing(): void
    {
        $this->timeTracking->stopRunningTimer();
        $this->timeTracking->stopTimerOf('a');

        self::assertSame([], $this->store->load());
    }

    public function testStoppingTheTimerOfAnItemLeavesTheTimerOfAnotherRunning(): void
    {
        $this->timeTracking->startTimer('b');
        $this->timeTracking->stopTimerOf('a');

        self::assertTrue($this->timeTracking->trackedItems()[1]->isRunning());

        $this->timeTracking->stopTimerOf('b');

        self::assertFalse($this->timeTracking->trackedItems()[1]->isRunning());
    }

    public function testATimerCannotBeStartedOnADoneItem(): void
    {
        $this->todoList->markDone('a');

        try {
            $this->timeTracking->startTimer('a');
            self::fail('A timer on a done item must be refused.');
        } catch (ItemIsDoneException $refused) {
            self::assertStringContainsString('done', $refused->getMessage());
        }
        self::assertSame([], $this->store->load());
    }

    public function testATimerCannotBeStartedOnAnItemNotOnTheList(): void
    {
        $this->expectException(ItemNotOnTheListException::class);

        $this->timeTracking->startTimer('unknown');
    }

    public function testTheTimeSpentIsKeptWhenTheItemIsDeletedAndNotShown(): void
    {
        $this->timeTracking->startTimer('a');
        $this->clock->sleep(42);
        $this->timeTracking->stopTimerOf('a');
        $this->todoList->delete('a');

        self::assertCount(1, $this->timeTracking->trackedItems());
        self::assertCount(1, $this->store->load());
        self::assertSame(42, $this->store->load()[0]->secondsAt(DateAndTimeService::getDateTimeImmutable()));
    }

    private function seconds(string $itemId): int
    {
        foreach ($this->timeTracking->trackedItems() as $item) {
            if ($item->itemId === $itemId) {
                return $item->seconds;
            }
        }

        self::fail("No item {$itemId}.");
    }
}
