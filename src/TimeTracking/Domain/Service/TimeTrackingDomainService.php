<?php

declare(strict_types=1);

namespace App\TimeTracking\Domain\Service;

use App\Common\Domain\Service\DateAndTimeService;
use App\TimeTracking\Domain\Exception\ItemIsDoneException;
use App\TimeTracking\Domain\Exception\ItemNotOnTheListException;
use App\TimeTracking\Domain\Model\TimeEntry;
use App\TimeTracking\Domain\Model\TrackedItem;
use App\Todo\Facade\Dto\TodoItemDto;
use App\Todo\Facade\TodoListFacadeInterface;
use DateTimeImmutable;

/**
 * The time tracking's use cases: read the items with their time, start a timer, stop it. At most one timer runs at a
 * time. Every rule is decided here.
 */
final readonly class TimeTrackingDomainService
{
    public function __construct(
        private TimeEntryStoreInterface $store,
        private TodoListFacadeInterface $todoList
    ) {
    }

    /**
     * @return list<TrackedItem> the items of the todo list, in its order
     */
    public function trackedItems(): array
    {
        $now     = DateAndTimeService::getDateTimeImmutable();
        $entries = $this->store->load();

        return array_map(
            static function (TodoItemDto $item) use ($entries, $now): TrackedItem {
                $seconds      = 0;
                $runningSince = null;
                foreach ($entries as $entry) {
                    if ($entry->itemId !== $item->id) {
                        continue;
                    }
                    $seconds += $entry->secondsAt($now);
                    if ($entry->isRunning()) {
                        $runningSince = $entry->startedAt;
                    }
                }

                return new TrackedItem(
                    $item->id,
                    $item->text,
                    $item->isDone,
                    $seconds,
                    $runningSince
                );
            },
            $this->todoList->items()
        );
    }

    /**
     * Starts the timer on the item and stops the timer that runs on another one. Starting a timer that runs already
     * changes nothing.
     *
     * @throws ItemNotOnTheListException
     * @throws ItemIsDoneException
     */
    public function startTimer(string $itemId): void
    {
        $item = $this->itemWithId($itemId);
        if ($item->isDone) {
            throw ItemIsDoneException::cannotStartTimer();
        }

        $now     = DateAndTimeService::getDateTimeImmutable();
        $entries = $this->store->load();
        foreach ($entries as $entry) {
            if ($entry->isRunning() && $entry->itemId === $itemId) {
                return;
            }
        }

        $entries   = $this->withTimersStopped($entries, null, $now);
        $entries[] = new TimeEntry($itemId, $now);
        $this->store->store($entries);
    }

    /**
     * Stopping a timer that does not run changes nothing.
     */
    public function stopTimer(string $itemId): void
    {
        $this->stopTimerAt($itemId, DateAndTimeService::getDateTimeImmutable());
    }

    /**
     * Stops the timer of the item as of the given time, as when the item was marked done or deleted.
     */
    public function stopTimerAt(string $itemId, DateTimeImmutable $stoppedAt): void
    {
        $entries = $this->store->load();
        if (!array_any(
            $entries,
            static fn (TimeEntry $entry): bool => $entry->isRunning() && $entry->itemId === $itemId
        )) {
            return;
        }

        $this->store->store($this->withTimersStopped($entries, $itemId, $stoppedAt));
    }

    /**
     * @param list<TimeEntry> $entries
     * @param ?string         $onlyItemId the item whose timer is stopped, or every running timer
     *
     * @return list<TimeEntry>
     */
    private function withTimersStopped(array $entries, ?string $onlyItemId, DateTimeImmutable $stoppedAt): array
    {
        return array_map(
            static fn (TimeEntry $entry): TimeEntry => $entry->isRunning() && ($onlyItemId === null || $entry->itemId === $onlyItemId)
                ? $entry->stoppedAt(max($stoppedAt, $entry->startedAt))
                : $entry,
            $entries
        );
    }

    /**
     * @throws ItemNotOnTheListException
     */
    private function itemWithId(string $itemId): TodoItemDto
    {
        foreach ($this->todoList->items() as $item) {
            if ($item->id === $itemId) {
                return $item;
            }
        }

        throw ItemNotOnTheListException::withId($itemId);
    }
}
