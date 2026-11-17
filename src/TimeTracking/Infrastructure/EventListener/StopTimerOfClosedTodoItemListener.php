<?php

declare(strict_types=1);

namespace App\TimeTracking\Infrastructure\EventListener;

use App\TimeTracking\Domain\Service\TimeTrackingDomainService;
use App\Todo\Facade\SymfonyEvent\TodoItemDeletedSymfonyEvent;
use App\Todo\Facade\SymfonyEvent\TodoItemDoneSymfonyEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * An item that is marked done or deleted on the todo list has no running timer any more.
 */
final readonly class StopTimerOfClosedTodoItemListener
{
    public function __construct(
        private TimeTrackingDomainService $timeTracking
    ) {
    }

    #[AsEventListener]
    public function onItemDone(TodoItemDoneSymfonyEvent $event): void
    {
        $this->timeTracking->stopTimer($event->itemId);
    }

    #[AsEventListener]
    public function onItemDeleted(TodoItemDeletedSymfonyEvent $event): void
    {
        $this->timeTracking->stopTimer($event->itemId);
    }
}
