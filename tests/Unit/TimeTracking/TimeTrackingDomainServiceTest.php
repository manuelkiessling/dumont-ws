<?php

declare(strict_types=1);

namespace App\Tests\Unit\TimeTracking;

use App\TimeTracking\Domain\Exception\TimeTrackingRefusedException;

final class TimeTrackingDomainServiceTest extends TimeTrackingTestCase
{
    public function testAnItemNeverTimedHasNoTimeEvenIfItIsOld(): void
    {
        $item = $this->todoList->addItem('Buy milk');
        $this->clock->sleep(3600);

        self::assertSame(0, $this->spent($item->id));
        self::assertFalse($this->isRunning($item->id));
    }

    public function testOnlyTheTimeBetweenStartAndStopCounts(): void
    {
        $item = $this->todoList->addItem('Buy milk');

        $this->clock->sleep(100);
        $this->timeTracking->startTimer($item->id);
        $this->clock->sleep(65);
        self::assertSame(65, $this->spent($item->id));
        self::assertTrue($this->isRunning($item->id));
        $this->timeTracking->stopTimer($item->id);
        $this->clock->sleep(500);

        self::assertSame(65, $this->spent($item->id));
        self::assertFalse($this->isRunning($item->id));
    }

    public function testTheTimeIsTheSumOfAllPeriods(): void
    {
        $item = $this->todoList->addItem('Buy milk');

        $this->timeTracking->startTimer($item->id);
        $this->clock->sleep(10);
        $this->timeTracking->stopTimer($item->id);
        $this->clock->sleep(1000);
        $this->timeTracking->startTimer($item->id);
        $this->clock->sleep(3661);

        self::assertSame(3671, $this->spent($item->id));
    }

    public function testTheRunningTimerReportsWhenItWasStarted(): void
    {
        $item = $this->todoList->addItem('Buy milk');
        $this->clock->sleep(5);

        $this->timeTracking->startTimer($item->id);

        $row = $this->timeTracking->trackedItems()[0];
        self::assertSame('2026-11-17 09:00:05', $row->runningSince?->format('Y-m-d H:i:s'));
    }

    public function testStartingATimerStopsTheTimerOnAnotherItem(): void
    {
        $first  = $this->todoList->addItem('First');
        $second = $this->todoList->addItem('Second');

        $this->timeTracking->startTimer($first->id);
        $this->clock->sleep(20);
        $this->timeTracking->startTimer($second->id);
        $this->clock->sleep(30);

        self::assertFalse($this->isRunning($first->id));
        self::assertSame(20, $this->spent($first->id));
        self::assertTrue($this->isRunning($second->id));
        self::assertSame(30, $this->spent($second->id));
    }

    public function testStartingARunningTimerAgainKeepsItGoing(): void
    {
        $item = $this->todoList->addItem('Buy milk');

        $this->timeTracking->startTimer($item->id);
        $this->clock->sleep(10);
        $this->timeTracking->startTimer($item->id);
        $this->clock->sleep(10);

        self::assertSame(20, $this->spent($item->id));
    }

    public function testStoppingATimerThatIsNotRunningChangesNothing(): void
    {
        $item = $this->todoList->addItem('Buy milk');

        $this->timeTracking->stopTimer($item->id);

        self::assertSame(0, $this->spent($item->id));
    }

    public function testAnItemMarkedDoneStopsItsTimer(): void
    {
        $item = $this->todoList->addItem('Buy milk');
        $this->timeTracking->startTimer($item->id);
        $this->clock->sleep(7);

        $this->todoList->markItemDone($item->id);
        $this->clock->sleep(100);

        self::assertFalse($this->isRunning($item->id));
        self::assertSame(7, $this->spent($item->id));
    }

    public function testADeletedItemsTimerStopsAndItsTimeIsKept(): void
    {
        $item  = $this->todoList->addItem('Buy milk');
        $other = $this->todoList->addItem('Other');
        $this->timeTracking->startTimer($item->id);
        $this->clock->sleep(7);

        $this->todoList->deleteItem($item->id);
        $this->clock->sleep(100);
        $this->timeTracking->startTimer($other->id);

        self::assertCount(1, $this->timeTracking->trackedItems());
        self::assertTrue($this->isRunning($other->id));
        // The stopped period of the deleted item is still kept
        $this->timeTracking->stopTimer($item->id);
        self::assertSame(0, $this->spent($other->id));
    }

    public function testNoTimerCanBeStartedOnADoneItem(): void
    {
        $item = $this->todoList->addItem('Buy milk');
        $this->todoList->markItemDone($item->id);

        $this->expectException(TimeTrackingRefusedException::class);
        $this->expectExceptionMessage('This item is done, so no timer can be started on it.');

        $this->timeTracking->startTimer($item->id);
    }

    public function testNoTimerCanBeStartedOnAnItemNotOnTheList(): void
    {
        $this->expectException(TimeTrackingRefusedException::class);

        $this->timeTracking->startTimer('unknown');
    }

    public function testAfterADoneItemIsRefusedTheRunningTimerGoesOn(): void
    {
        $open = $this->todoList->addItem('Open');
        $done = $this->todoList->addItem('Done');
        $this->todoList->markItemDone($done->id);
        $this->timeTracking->startTimer($open->id);

        try {
            $this->timeTracking->startTimer($done->id);
            self::fail('Expected a refusal');
        } catch (TimeTrackingRefusedException) {
        }

        self::assertTrue($this->isRunning($open->id));
    }
}
