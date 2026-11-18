<?php

declare(strict_types=1);

namespace App\Tests\Integration\TimeTracking;

use App\Common\Domain\Service\DateAndTimeService;
use App\TimeTracking\Domain\Model\TimeEntry;
use App\TimeTracking\Domain\Service\TimeEntryStoreInterface;
use App\TimeTracking\Domain\Service\TimeTrackingDomainService;
use App\Todo\Domain\Service\TodoListDomainService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;

/**
 * What the container wires: the store on a real file, and the todo list's events stopping timers.
 */
final class TimeTrackingWiringTest extends KernelTestCase
{
    private TimeEntryStoreInterface $store;
    private TimeTrackingDomainService $timeTracking;
    private TodoListDomainService $todoList;
    private MockClock $clock;

    protected function setUp(): void
    {
        foreach (['app.todo_items_file', 'app.time_entries_file'] as $parameter) {
            $file = self::getContainer()->getParameter($parameter);
            self::assertIsString($file);
            if (is_file($file)) {
                unlink($file);
            }
        }

        $this->clock = new MockClock('2026-11-17 09:00:00');
        Clock::set($this->clock);

        $store = self::getContainer()->get(TimeEntryStoreInterface::class);
        self::assertInstanceOf(TimeEntryStoreInterface::class, $store);
        $timeTracking = self::getContainer()->get(TimeTrackingDomainService::class);
        self::assertInstanceOf(TimeTrackingDomainService::class, $timeTracking);
        $todoList = self::getContainer()->get(TodoListDomainService::class);
        self::assertInstanceOf(TodoListDomainService::class, $todoList);

        $this->store        = $store;
        $this->timeTracking = $timeTracking;
        $this->todoList     = $todoList;
    }

    public function testWithoutAFileThereAreNoEntries(): void
    {
        self::assertSame([], $this->store->load());
    }

    public function testStoredEntriesAreReadBackAsTheyWere(): void
    {
        $entries = [
            new TimeEntry(
                'a',
                DateAndTimeService::getDateTimeImmutable('2026-11-17 09:00:00'),
                DateAndTimeService::getDateTimeImmutable('2026-11-17 09:01:30')
            ),
            new TimeEntry('b', DateAndTimeService::getDateTimeImmutable('2026-11-17 09:02:00')),
        ];

        $this->store->store($entries);

        self::assertEquals($entries, $this->store->load());
    }

    public function testMarkingTheItemDoneStopsItsRunningTimer(): void
    {
        $item = $this->todoList->addItem('Buy milk');
        $this->timeTracking->startTimer($item->id);
        $this->clock->sleep(30);

        $this->todoList->markItemDone($item->id);
        $this->clock->sleep(300);

        $tracked = $this->timeTracking->trackedItems();
        self::assertSame(30, $tracked[0]->secondsSpent);
        self::assertFalse($tracked[0]->isRunning());
    }

    public function testDeletingTheItemStopsItsTimerAndKeepsItsTime(): void
    {
        $item = $this->todoList->addItem('Buy milk');
        $this->timeTracking->startTimer($item->id);
        $this->clock->sleep(30);

        $this->todoList->deleteItem($item->id);
        $this->clock->sleep(300);

        self::assertSame([], $this->timeTracking->trackedItems());
        $entries = $this->store->load();
        self::assertCount(1, $entries);
        self::assertSame(30, $entries[0]->secondsAt(DateAndTimeService::getDateTimeImmutable()));
        self::assertFalse($entries[0]->isRunning());
    }
}
