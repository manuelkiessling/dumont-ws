<?php

declare(strict_types=1);

namespace App\Tests\Unit\TimeTracking;

use App\Common\Domain\Service\DateAndTimeService;
use App\TimeTracking\Domain\Exception\ItemIsDoneException;
use App\TimeTracking\Domain\Exception\ItemNotOnTheListException;
use App\TimeTracking\Domain\Model\TrackedItem;
use PHPUnit\Framework\TestCase;

final class TimeTrackingDomainServiceTest extends TestCase
{
    private TimeTrackingWorld $world;

    protected function setUp(): void
    {
        $this->world = new TimeTrackingWorld();
    }

    public function testAnItemWithoutATimerHasNoTimeNoMatterHowOldItIs(): void
    {
        $this->world->todoList->addItem('Buy milk');
        $this->world->passSeconds(3600);

        $item = $this->tracked('Buy milk');

        self::assertSame(0, $this->seconds($item));
        self::assertFalse($item->isRunning());
        self::assertFalse($item->isDeleted);
    }

    public function testATimerCountsOnlyTheTimeBetweenStartAndStop(): void
    {
        $milk = $this->world->todoList->addItem('Buy milk');
        $this->world->passSeconds(100);

        $this->world->timeTracking->startTimer($milk->id);
        $this->world->passSeconds(65);
        self::assertSame(65, $this->seconds($this->tracked('Buy milk')));

        $this->world->timeTracking->stopTimer($milk->id);
        $this->world->passSeconds(500);

        $item = $this->tracked('Buy milk');
        self::assertSame(65, $this->seconds($item));
        self::assertFalse($item->isRunning());
    }

    public function testTheTimeIsTheSumOfAllThePeriodsTheTimerRan(): void
    {
        $milk = $this->world->todoList->addItem('Buy milk');

        $this->world->timeTracking->startTimer($milk->id);
        $this->world->passSeconds(10);
        $this->world->timeTracking->stopTimer($milk->id);
        $this->world->passSeconds(1000);
        $this->world->timeTracking->startTimer($milk->id);
        $this->world->passSeconds(5);

        self::assertSame(15, $this->seconds($this->tracked('Buy milk')));
    }

    public function testARunningTimerKnowsWhenItWasStarted(): void
    {
        $milk = $this->world->todoList->addItem('Buy milk');
        $this->world->passSeconds(30);
        $this->world->timeTracking->startTimer($milk->id);
        $this->world->passSeconds(7);

        $item = $this->tracked('Buy milk');

        self::assertTrue($item->isRunning());
        self::assertSame('2026-11-17 09:00:30', $item->runningSince()?->format('Y-m-d H:i:s'));
    }

    public function testStartingATimerStopsTheOneThatRuns(): void
    {
        $milk = $this->world->todoList->addItem('Buy milk');
        $mum  = $this->world->todoList->addItem('Call mum');

        $this->world->timeTracking->startTimer($milk->id);
        $this->world->passSeconds(20);
        $this->world->timeTracking->startTimer($mum->id);
        $this->world->passSeconds(5);

        $milkItem = $this->tracked('Buy milk');
        $mumItem  = $this->tracked('Call mum');
        self::assertFalse($milkItem->isRunning());
        self::assertSame(20, $this->seconds($milkItem));
        self::assertTrue($mumItem->isRunning());
        self::assertSame(5, $this->seconds($mumItem));
    }

    public function testStartingARunningTimerAgainChangesNothing(): void
    {
        $milk = $this->world->todoList->addItem('Buy milk');
        $this->world->timeTracking->startTimer($milk->id);
        $this->world->passSeconds(10);

        $this->world->timeTracking->startTimer($milk->id);
        $this->world->passSeconds(10);

        $item = $this->tracked('Buy milk');
        self::assertSame(20, $this->seconds($item));
        self::assertSame('2026-11-17 09:00:00', $item->runningSince()?->format('Y-m-d H:i:s'));
    }

    public function testStoppingATimerThatDoesNotRunChangesNothing(): void
    {
        $milk = $this->world->todoList->addItem('Buy milk');

        $this->world->timeTracking->stopTimer($milk->id);

        self::assertSame(0, $this->seconds($this->tracked('Buy milk')));
    }

    public function testMarkingAnItemDoneStopsItsTimer(): void
    {
        $milk = $this->world->todoList->addItem('Buy milk');
        $this->world->timeTracking->startTimer($milk->id);
        $this->world->passSeconds(12);

        $this->world->todoList->markItemDone($milk->id);
        $this->world->passSeconds(100);

        $item = $this->tracked('Buy milk');
        self::assertTrue($item->isDone);
        self::assertFalse($item->isRunning());
        self::assertSame(12, $this->seconds($item));
    }

    public function testMarkingAnotherItemDoneLeavesTheRunningTimerAlone(): void
    {
        $milk = $this->world->todoList->addItem('Buy milk');
        $mum  = $this->world->todoList->addItem('Call mum');
        $this->world->timeTracking->startTimer($milk->id);

        $this->world->todoList->markItemDone($mum->id);

        self::assertTrue($this->tracked('Buy milk')->isRunning());
    }

    public function testNoTimerCanBeStartedOnADoneItem(): void
    {
        $milk = $this->world->todoList->addItem('Buy milk');
        $this->world->todoList->markItemDone($milk->id);

        $this->expectException(ItemIsDoneException::class);

        $this->world->timeTracking->startTimer($milk->id);
    }

    public function testTheTimeOfADeletedItemIsKeptAndItsTimerStopped(): void
    {
        $milk = $this->world->todoList->addItem('Buy milk');
        $this->world->timeTracking->startTimer($milk->id);
        $this->world->passSeconds(42);

        $this->world->todoList->deleteItem($milk->id);
        $this->world->passSeconds(100);

        $item = $this->tracked('Buy milk');
        self::assertTrue($item->isDeleted);
        self::assertFalse($item->isRunning());
        self::assertSame(42, $this->seconds($item));
    }

    public function testNoTimerCanBeStartedOnAnItemNotOnTheList(): void
    {
        $milk = $this->world->todoList->addItem('Buy milk');
        $this->world->todoList->deleteItem($milk->id);

        $this->expectException(ItemNotOnTheListException::class);

        $this->world->timeTracking->startTimer($milk->id);
    }

    public function testItemsWithoutTimeAreNotKeptOnceDeleted(): void
    {
        $milk = $this->world->todoList->addItem('Buy milk');
        $this->world->todoList->deleteItem($milk->id);

        self::assertSame([], $this->world->timeTracking->trackedItems());
    }

    private function tracked(string $text): TrackedItem
    {
        foreach ($this->world->timeTracking->trackedItems() as $item) {
            if ($item->text === $text) {
                return $item;
            }
        }

        self::fail("No tracked item {$text}.");
    }

    private function seconds(TrackedItem $item): int
    {
        return $item->secondsAt(DateAndTimeService::getDateTimeImmutable());
    }
}
