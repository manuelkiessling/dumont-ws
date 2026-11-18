<?php

declare(strict_types=1);

namespace App\Tests\Integration\TimeTracking;

use App\TimeTracking\Domain\Service\TimeTrackingDomainService;
use App\Todo\Domain\Service\TodoListDomainService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;

/**
 * What the container wires: the todo list and time tracking of the real services, on real files. An item that is
 * marked done or deleted on the todo list loses its running timer, and its time stays.
 */
final class StopTimerOfItemListenerTest extends KernelTestCase
{
    private MockClock $clock;
    private TodoListDomainService $todoList;
    private TimeTrackingDomainService $timeTracking;

    protected function setUp(): void
    {
        foreach (['app.todo_items_file', 'app.time_periods_file'] as $parameter) {
            $file = self::getContainer()->getParameter($parameter);
            self::assertIsString($file);
            if (is_file($file)) {
                unlink($file);
            }
        }

        $this->clock = new MockClock('2026-11-17 09:00:00');
        Clock::set($this->clock);

        $todoList = self::getContainer()->get(TodoListDomainService::class);
        self::assertInstanceOf(TodoListDomainService::class, $todoList);
        $timeTracking = self::getContainer()->get(TimeTrackingDomainService::class);
        self::assertInstanceOf(TimeTrackingDomainService::class, $timeTracking);
        $this->todoList     = $todoList;
        $this->timeTracking = $timeTracking;
    }

    public function testMarkingTheItemDoneStopsItsTimer(): void
    {
        $item = $this->todoList->addItem('Write report');
        $this->timeTracking->startTimer($item->id);
        $this->clock->sleep(30);

        $this->todoList->markItemDone($item->id);
        $this->clock->sleep(100);

        $tracked = $this->timeTracking->trackedItems()[0];
        self::assertFalse($tracked->isRunning());
        self::assertSame(30, $tracked->seconds);
    }

    public function testDeletingTheItemStopsItsTimerAndKeepsTheTime(): void
    {
        $item  = $this->todoList->addItem('Write report');
        $other = $this->todoList->addItem('Call mum');
        $this->timeTracking->startTimer($item->id);
        $this->clock->sleep(30);

        $this->todoList->deleteItem($item->id);
        $this->timeTracking->startTimer($other->id);
        $this->clock->sleep(100);

        $items = $this->timeTracking->trackedItems();
        self::assertCount(1, $items);
        self::assertSame(100, $items[0]->seconds);

        $file = self::getContainer()->getParameter('app.time_periods_file');
        self::assertIsString($file);
        self::assertStringContainsString($item->id, (string) file_get_contents($file));
    }

    public function testDeletingAnItemWithoutATimerChangesNothing(): void
    {
        $item = $this->todoList->addItem('Write report');

        $this->todoList->deleteItem($item->id);

        $file = self::getContainer()->getParameter('app.time_periods_file');
        self::assertIsString($file);
        self::assertFileDoesNotExist($file);
    }
}
