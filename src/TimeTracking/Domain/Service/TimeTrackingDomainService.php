<?php

declare(strict_types=1);

namespace App\TimeTracking\Domain\Service;

use App\Common\Domain\Service\DateAndTimeService;
use App\TimeTracking\Domain\Exception\ItemCannotBeTrackedException;
use App\TimeTracking\Domain\Model\TimeEntry;
use App\Todo\Facade\TodoItemsInterface;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Tracks the time spent on todo items: one timer runs at a time, on an item that is on the todo list and open.
 */
final readonly class TimeTrackingDomainService
{
    public function __construct(
        private TimeEntryStoreInterface $store,
        private TodoItemsInterface      $todoItems
    ) {
    }

    /**
     * Starting the timer of an item stops the timer that runs for another one. Starting the timer that runs already
     * changes nothing.
     *
     * @throws ItemCannotBeTrackedException
     */
    public function startTimer(string $itemId): void
    {
        $item = $this->todoItems->itemById($itemId);
        if ($item === null) {
            throw ItemCannotBeTrackedException::notOnTheList();
        }
        if ($item->isDone) {
            throw ItemCannotBeTrackedException::done();
        }

        if ($this->runningEntry()?->itemId === $itemId) {
            return;
        }

        $now       = DateAndTimeService::getDateTimeImmutable();
        $entries   = $this->stopped($this->store->load(), $now);
        $entries[] = new TimeEntry(Uuid::v7()->toRfc4122(), $itemId, $now);
        $this->store->store($entries);
    }

    /**
     * Stopping the timer when none runs changes nothing.
     */
    public function stopTimer(): void
    {
        if ($this->runningEntry() === null) {
            return;
        }

        $this->store->store($this->stopped($this->store->load(), DateAndTimeService::getDateTimeImmutable()));
    }

    /**
     * Stops the timer if it runs for the given item, and changes nothing otherwise.
     */
    public function stopTimerOfItem(string $itemId): void
    {
        if ($this->runningEntry()?->itemId === $itemId) {
            $this->stopTimer();
        }
    }

    public function runningEntry(): ?TimeEntry
    {
        foreach ($this->store->load() as $entry) {
            if ($entry->isRunning()) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * All the time spent on an item, including the timer that runs for it until now.
     */
    public function trackedSeconds(string $itemId): int
    {
        $now     = DateAndTimeService::getDateTimeImmutable();
        $seconds = 0;

        foreach ($this->store->load() as $entry) {
            if ($entry->itemId === $itemId) {
                $seconds += $entry->seconds($now);
            }
        }

        return $seconds;
    }

    /**
     * @param list<TimeEntry> $entries
     *
     * @return list<TimeEntry> the entries with the running one stopped
     */
    private function stopped(array $entries, DateTimeImmutable $now): array
    {
        return array_map(
            static fn (TimeEntry $entry): TimeEntry => $entry->isRunning() ? $entry->stoppedAt($now) : $entry,
            $entries
        );
    }
}
