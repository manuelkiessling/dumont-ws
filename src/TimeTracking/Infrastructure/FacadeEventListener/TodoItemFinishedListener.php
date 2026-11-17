<?php

declare(strict_types=1);

namespace App\TimeTracking\Infrastructure\FacadeEventListener;

use App\TimeTracking\Domain\Service\TimeTrackingDomainService;
use App\Todo\Facade\SymfonyEvent\TodoItemDeletedSymfonyEvent;
use App\Todo\Facade\SymfonyEvent\TodoItemMarkedDoneSymfonyEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * An item that is done or deleted is no longer worked on: its timer stops.
 */
final readonly class TodoItemFinishedListener
{
    public function __construct(
        private TimeTrackingDomainService $timeTracking
    ) {
    }

    #[AsEventListener]
    public function onMarkedDone(TodoItemMarkedDoneSymfonyEvent $event): void
    {
        $this->timeTracking->stopTimerOfItem($event->itemId);
    }

    #[AsEventListener]
    public function onDeleted(TodoItemDeletedSymfonyEvent $event): void
    {
        $this->timeTracking->stopTimerOfItem($event->itemId);
    }
}
