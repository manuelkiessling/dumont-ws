<?php

declare(strict_types=1);

namespace App\Tests\Integration\TimeTracking;

use App\TimeTracking\Domain\Service\TimeTrackingDomainService;
use App\Todo\Domain\Service\TodoListDomainService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The todo list and time tracking as the application wires them: what happens to an item stops its timer.
 */
final class TodoItemFinishedListenerTest extends KernelTestCase
{
    private TodoListDomainService $todoList;
    private TimeTrackingDomainService $timeTracking;

    protected function setUp(): void
    {
        foreach (['app.todo_items_file', 'app.time_entries_file'] as $parameter) {
            $file = self::getContainer()->getParameter($parameter);
            self::assertIsString($file);
            if (is_file($file)) {
                unlink($file);
            }
        }

        $todoList     = self::getContainer()->get(TodoListDomainService::class);
        $timeTracking = self::getContainer()->get(TimeTrackingDomainService::class);
        self::assertInstanceOf(TodoListDomainService::class, $todoList);
        self::assertInstanceOf(TimeTrackingDomainService::class, $timeTracking);
        $this->todoList     = $todoList;
        $this->timeTracking = $timeTracking;
    }

    public function testMarkingAnItemDoneStopsItsTimer(): void
    {
        $item = $this->todoList->addItem('Buy milk');
        $this->timeTracking->startTimer($item->id);

        $this->todoList->markItemDone($item->id);

        self::assertNull($this->timeTracking->runningEntry());
    }

    public function testDeletingAnItemStopsItsTimer(): void
    {
        $item = $this->todoList->addItem('Buy milk');
        $this->timeTracking->startTimer($item->id);

        $this->todoList->deleteItem($item->id);

        self::assertNull($this->timeTracking->runningEntry());
    }

    public function testFinishingAnotherItemLeavesTheTimerRunning(): void
    {
        $tracked = $this->todoList->addItem('Buy milk');
        $other   = $this->todoList->addItem('Call mum');
        $this->timeTracking->startTimer($tracked->id);

        $this->todoList->markItemDone($other->id);

        self::assertSame($tracked->id, $this->timeTracking->runningEntry()?->itemId);
    }
}
