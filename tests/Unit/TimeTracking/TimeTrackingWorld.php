<?php

declare(strict_types=1);

namespace App\Tests\Unit\TimeTracking;

use App\Tests\Unit\Todo\InMemoryTodoItemStore;
use App\TimeTracking\Domain\Service\TimeTrackingDomainService;
use App\TimeTracking\Infrastructure\EventListener\StopTimerOfClosedTodoItemListener;
use App\Todo\Domain\Service\TodoListDomainService;
use App\Todo\Facade\SymfonyEvent\TodoItemDeletedSymfonyEvent;
use App\Todo\Facade\SymfonyEvent\TodoItemDoneSymfonyEvent;
use App\Todo\Facade\TodoListFacade;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * The todo list and the time tracking wired as in the application, on in-memory stores and a clock that stands still
 * until the test moves it.
 */
final readonly class TimeTrackingWorld
{
    public TodoListDomainService $todoList;
    public TimeTrackingDomainService $timeTracking;
    private MockClock $clock;

    public function __construct(string $now = '2026-11-17 09:00:00')
    {
        $this->clock = new MockClock($now);
        Clock::set($this->clock);

        $dispatcher         = new EventDispatcher();
        $this->todoList     = new TodoListDomainService(new InMemoryTodoItemStore(), $dispatcher);
        $this->timeTracking = new TimeTrackingDomainService(
            new InMemoryTimeRecordStore(),
            new TodoListFacade($this->todoList)
        );

        $listener = new StopTimerOfClosedTodoItemListener($this->timeTracking);
        $dispatcher->addListener(TodoItemDoneSymfonyEvent::class, $listener->onItemDone(...));
        $dispatcher->addListener(TodoItemDeletedSymfonyEvent::class, $listener->onItemDeleted(...));
    }

    public function passSeconds(int $seconds): void
    {
        $this->clock->sleep($seconds);
    }
}
