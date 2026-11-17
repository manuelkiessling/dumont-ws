<?php

declare(strict_types=1);

namespace App\Tests\Unit\TimeTracking;

use App\Common\Domain\Service\DateAndTimeService;
use App\TimeTracking\Domain\Exception\ItemCannotBeTrackedException;
use App\TimeTracking\Domain\Service\TimeTrackingDomainService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;

final class TimeTrackingDomainServiceTest extends TestCase
{
    private const string MILK = '0195f0a2-5c3b-7d4e-8f00-000000000001';
    private const string MUM  = '0195f0a2-5c3b-7d4e-8f00-000000000002';
    private const string DONE = '0195f0a2-5c3b-7d4e-8f00-000000000003';

    private MockClock $clock;
    private TimeTrackingDomainService $timeTracking;

    protected function setUp(): void
    {
        $todoItems = new FakeTodoItems();
        $todoItems->add(self::MILK, 'Buy milk');
        $todoItems->add(self::MUM, 'Call mum');
        $todoItems->add(self::DONE, 'Water plants', true);

        $this->clock = new MockClock('2026-11-17 09:00:00');
        Clock::set($this->clock);

        $this->timeTracking = new TimeTrackingDomainService(new InMemoryTimeEntryStore(), $todoItems);
    }

    public function testARunningTimerCountsUntilNow(): void
    {
        $this->timeTracking->startTimer(self::MILK);
        $this->clock->sleep(90);

        self::assertSame(self::MILK, $this->timeTracking->runningEntry()?->itemId);
        self::assertSame(90, $this->timeTracking->trackedSeconds(self::MILK));
    }

    public function testAStoppedTimerCountsNoMore(): void
    {
        $this->timeTracking->startTimer(self::MILK);
        $this->clock->sleep(60);
        $this->timeTracking->stopTimer();
        $this->clock->sleep(60);

        self::assertNull($this->timeTracking->runningEntry());
        self::assertSame(60, $this->timeTracking->trackedSeconds(self::MILK));
    }

    public function testTheTimesOfAnItemAddUp(): void
    {
        $this->timeTracking->startTimer(self::MILK);
        $this->clock->sleep(60);
        $this->timeTracking->stopTimer();
        $this->timeTracking->startTimer(self::MILK);
        $this->clock->sleep(30);

        self::assertSame(90, $this->timeTracking->trackedSeconds(self::MILK));
    }

    public function testStartingATimerStopsTheOneThatRuns(): void
    {
        $this->timeTracking->startTimer(self::MILK);
        $this->clock->sleep(60);
        $this->timeTracking->startTimer(self::MUM);
        $this->clock->sleep(30);

        self::assertSame(self::MUM, $this->timeTracking->runningEntry()?->itemId);
        self::assertSame(60, $this->timeTracking->trackedSeconds(self::MILK));
        self::assertSame(30, $this->timeTracking->trackedSeconds(self::MUM));
    }

    public function testStartingTheTimerThatRunsChangesNothing(): void
    {
        $this->timeTracking->startTimer(self::MILK);
        $this->clock->sleep(60);
        $this->timeTracking->startTimer(self::MILK);

        self::assertEquals(DateAndTimeService::getDateTimeImmutable('2026-11-17 09:00:00'), $this->timeTracking->runningEntry()?->startedAt);
    }

    public function testStoppingWhenNoTimerRunsChangesNothing(): void
    {
        $this->timeTracking->stopTimer();

        self::assertNull($this->timeTracking->runningEntry());
    }

    public function testTheTimerOfAnItemIsStoppedOnlyIfItRunsForIt(): void
    {
        $this->timeTracking->startTimer(self::MILK);

        $this->timeTracking->stopTimerOfItem(self::MUM);
        self::assertSame(self::MILK, $this->timeTracking->runningEntry()?->itemId);

        $this->timeTracking->stopTimerOfItem(self::MILK);
        self::assertNull($this->timeTracking->runningEntry());
    }

    public function testADoneItemCannotBeTracked(): void
    {
        $this->expectExceptionObject(ItemCannotBeTrackedException::done());

        $this->timeTracking->startTimer(self::DONE);
    }

    public function testAnItemNotOnTheTodoListCannotBeTracked(): void
    {
        $this->expectExceptionObject(ItemCannotBeTrackedException::notOnTheList());

        $this->timeTracking->startTimer('0195f0a2-5c3b-7d4e-8f00-0000000000ff');
    }
}
