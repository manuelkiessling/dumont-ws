<?php

declare(strict_types=1);

namespace App\TimeTracking\Domain\Service;

use App\Common\Domain\Service\DateAndTimeService;
use App\TimeTracking\Domain\Exception\TimeTrackingRefusedException;
use App\TimeTracking\Domain\Model\TimePeriod;
use App\TimeTracking\Domain\Model\TrackedItem;
use App\Todo\Domain\Model\TodoItem;
use App\Todo\Domain\Service\TodoListDomainService;

/**
 * The time tracking's use cases: read the time spent per item, start a timer, stop it. Only one timer runs at a
 * time. An item's time is the sum of the periods its timer ran.
 */
final readonly class TimeTrackingDomainService
{
    public function __construct(
        private TimePeriodStoreInterface $periods,
        private TodoListDomainService $todoList
    ) {
    }

    /**
     * @return list<TrackedItem> the items of the todo list, in list order
     */
    public function trackedItems(): array
    {
        $now     = DateAndTimeService::getDateTimeImmutable();
        $periods = $this->periods->load();

        return array_map(function (TodoItem $item) use ($periods, $now): TrackedItem {
            $seconds      = 0.0;
            $runningSince = null;

            foreach ($periods as $period) {
                if ($period->itemId !== $item->id) {
                    continue;
                }
                $seconds += $period->seconds($now);
                if ($period->isRunning()) {
                    $runningSince = $period->startedAt;
                }
            }

            return new TrackedItem($item, (int) floor($seconds), $runningSince);
        }, $this->todoList->items());
    }

    /**
     * Starts the item's timer and stops the timer running on another item. Starting a timer that runs already
     * changes nothing.
     *
     * @throws TimeTrackingRefusedException
     */
    public function startTimer(string $itemId): void
    {
        $item = null;
        foreach ($this->todoList->items() as $candidate) {
            if ($candidate->id === $itemId) {
                $item = $candidate;
            }
        }
        if ($item === null) {
            throw TimeTrackingRefusedException::itemNotOnTheList();
        }
        if ($item->isDone()) {
            throw TimeTrackingRefusedException::itemIsDone();
        }

        $now     = DateAndTimeService::getDateTimeImmutable();
        $periods = $this->periods->load();
        $running = false;

        foreach ($periods as $index => $period) {
            if (!$period->isRunning()) {
                continue;
            }
            if ($period->itemId === $itemId) {
                $running = true;
            } else {
                $periods[$index] = $period->stoppedAt($now);
            }
        }
        if (!$running) {
            $periods[] = new TimePeriod($itemId, $now);
        }

        $this->periods->store($periods);
    }

    /**
     * Stopping a timer that is not running changes nothing.
     */
    public function stopTimer(string $itemId): void
    {
        new StopsTimer($this->periods)->stop($itemId);
    }
}
