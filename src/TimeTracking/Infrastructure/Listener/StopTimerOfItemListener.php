<?php

declare(strict_types=1);

namespace App\TimeTracking\Infrastructure\Listener;

use App\TimeTracking\Domain\Service\TimeTrackingDomainService;
use App\Todo\Facade\SymfonyEvent\ItemDeletedSymfonyEvent;
use App\Todo\Facade\SymfonyEvent\ItemMarkedDoneSymfonyEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * An item that is marked done or deleted on the todo list has no running timer any more.
 */
final readonly class StopTimerOfItemListener
{
    public function __construct(
        private TimeTrackingDomainService $timeTracking
    ) {
    }

    #[AsEventListener]
    public function onItemMarkedDone(ItemMarkedDoneSymfonyEvent $event): void
    {
        $this->timeTracking->stopTimerOf($event->itemId);
    }

    #[AsEventListener]
    public function onItemDeleted(ItemDeletedSymfonyEvent $event): void
    {
        $this->timeTracking->stopTimerOf($event->itemId);
    }
}
