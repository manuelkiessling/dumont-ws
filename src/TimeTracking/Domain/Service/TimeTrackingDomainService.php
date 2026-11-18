<?php

declare(strict_types=1);

namespace App\TimeTracking\Domain\Service;

use App\Common\Domain\Service\DateAndTimeService;
use App\TimeTracking\Domain\Exception\ItemIsDoneException;
use App\TimeTracking\Domain\Exception\ItemNotOnTheListException;
use App\TimeTracking\Domain\Model\TimePeriod;
use App\TimeTracking\Domain\Model\TrackedItem;
use App\Todo\Facade\Dto\TodoItemDto;
use App\Todo\Facade\TodoListFacadeInterface;
use DateTimeImmutable;

/**
 * The use cases of time tracking: read the items with their times, start the timer of an item, stop it. Only one
 * timer runs at a time. Times are kept to the second.
 */
final readonly class TimeTrackingDomainService
{
    public function __construct(
        private TimePeriodStoreInterface $store,
        private TodoListFacadeInterface  $todoList
    ) {
    }

    /**
     * @return list<TrackedItem> the items of the todo list, in its order
     */
    public function trackedItems(): array
    {
        $periods = $this->store->load();
        $now     = self::now();

        return array_map(
            static function (TodoItemDto $item) use ($periods, $now): TrackedItem {
                $seconds      = 0;
                $runningSince = null;
                foreach ($periods as $period) {
                    if ($period->itemId !== $item->id) {
                        continue;
                    }
                    $seconds += $period->secondsAt($now);
                    if ($period->isRunning()) {
                        $runningSince = $period->startedAt;
                    }
                }

                return new TrackedItem(
                    $item->id,
                    $item->text,
                    $item->done,
                    $seconds,
                    $runningSince
                );
            },
            $this->todoList->items()
        );
    }

    /**
     * Starts the timer of the item and stops the one that runs on another item. Starting the timer of the item that
     * runs already changes nothing and succeeds.
     *
     * @throws ItemNotOnTheListException
     * @throws ItemIsDoneException
     */
    public function startTimer(string $itemId): void
    {
        $item = $this->itemWithId($itemId);
        if ($item->done) {
            throw ItemIsDoneException::withId($itemId);
        }

        $periods = $this->store->load();
        foreach ($periods as $period) {
            if ($period->isRunning() && $period->itemId === $itemId) {
                return;
            }
        }

        $now       = self::now();
        $periods   = $this->stopped($periods, null, $now);
        $periods[] = new TimePeriod($itemId, $now);
        $this->store->store($periods);
    }

    /**
     * Stops the timer that runs, on whichever item. Without a running timer nothing changes.
     */
    public function stopRunningTimer(): void
    {
        $this->stopTimers(null);
    }

    /**
     * Stops the timer of the item, if it runs, also when the item is gone from the todo list.
     */
    public function stopTimerOf(string $itemId): void
    {
        $this->stopTimers($itemId);
    }

    private function stopTimers(?string $itemId): void
    {
        $periods = $this->store->load();
        foreach ($periods as $period) {
            if ($period->isRunning() && ($itemId === null || $period->itemId === $itemId)) {
                $this->store->store($this->stopped($periods, $itemId, self::now()));

                return;
            }
        }
    }

    /**
     * @param list<TimePeriod> $periods
     *
     * @return list<TimePeriod> with the running periods of the item (of any item, for null) stopped
     */
    private function stopped(array $periods, ?string $itemId, DateTimeImmutable $at): array
    {
        return array_map(
            static fn (TimePeriod $period): TimePeriod => $period->isRunning() && ($itemId === null || $period->itemId === $itemId)
                ? $period->stoppedAt($at)
                : $period,
            $periods
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

    /**
     * The current time cut to the second: times are tracked and shown to the second.
     */
    private static function now(): DateTimeImmutable
    {
        $now = DateAndTimeService::getDateTimeImmutable();

        return $now->setTimestamp($now->getTimestamp());
    }
}
