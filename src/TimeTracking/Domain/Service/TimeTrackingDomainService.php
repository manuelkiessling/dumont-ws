<?php

declare(strict_types=1);

namespace App\TimeTracking\Domain\Service;

use App\Common\Domain\Service\DateAndTimeService;
use App\TimeTracking\Domain\Exception\TimeTrackingRefusedException;
use App\TimeTracking\Domain\Model\TimeEntry;
use App\TimeTracking\Domain\Model\TrackedItem;
use App\Todo\Domain\Model\TodoItem;
use App\Todo\Domain\Service\TimerStopperInterface;
use App\Todo\Domain\Service\TodoItemStoreInterface;
use DateTimeImmutable;

final readonly class TimeTrackingDomainService implements TimerStopperInterface
{
    public function __construct(
        private TimeEntryStoreInterface $entries,
        private TodoItemStoreInterface $todoItems
    ) {
    }

    /** The items of the todo list, followed by deleted items that time was spent on. @return list<TrackedItem> */
    public function trackedItems(): array
    {
        $now     = self::now();
        $entries = $this->entries->load();
        $tracked = [];
        $listed  = [];

        foreach ($this->todoItems->load() as $item) {
            $listed[$item->id] = true;
            $tracked[]         = $this->trackedItem($item->id, $item->text, $item->isDone(), false, $entries, $now);
        }

        $deleted = [];
        foreach ($entries as $entry) {
            if (!isset($listed[$entry->itemId]) && !isset($deleted[$entry->itemId])) {
                $deleted[$entry->itemId] = true;
                $tracked[]               = $this->trackedItem($entry->itemId, $entry->itemText, false, true, $entries, $now);
            }
        }

        return $tracked;
    }

    public function startTimer(string $itemId): void
    {
        $item = $this->findItem($itemId) ?? throw TimeTrackingRefusedException::itemNotOnTheList();
        if ($item->isDone()) {
            throw TimeTrackingRefusedException::itemIsDone();
        }

        $now     = self::now();
        $entries = $this->entries->load();

        foreach ($entries as $entry) {
            if ($entry->isRunning() && $entry->itemId === $itemId) {
                return;
            }
        }

        $entries   = $this->withTimersStopped($entries, null, $now);
        $entries[] = new TimeEntry($item->id, $item->text, $now);
        $this->entries->store($entries);
    }

    public function stopTimerOf(string $itemId): void
    {
        $entries = $this->entries->load();
        $stopped = $this->withTimersStopped($entries, $itemId, self::now());

        if ($stopped != $entries) {
            $this->entries->store($stopped);
        }
    }

    /** @param list<TimeEntry> $entries @return list<TimeEntry> */
    private function withTimersStopped(array $entries, ?string $onlyItemId, DateTimeImmutable $now): array
    {
        return array_map(
            fn (TimeEntry $entry): TimeEntry => $entry->isRunning() && ($onlyItemId === null || $entry->itemId === $onlyItemId)
                ? $entry->stoppedAt($now)
                : $entry,
            $entries
        );
    }

    /** @param list<TimeEntry> $entries */
    private function trackedItem(string $id, string $text, bool $isDone, bool $isDeleted, array $entries, DateTimeImmutable $now): TrackedItem
    {
        $seconds      = 0;
        $runningSince = null;

        foreach ($entries as $entry) {
            if ($entry->itemId !== $id) {
                continue;
            }
            $seconds += $entry->secondsAt($now);
            if ($entry->isRunning()) {
                $runningSince = $entry->startedAt;
            }
        }

        return new TrackedItem($id, $text, $isDone, $isDeleted, $seconds, $runningSince);
    }

    private function findItem(string $itemId): ?TodoItem
    {
        foreach ($this->todoItems->load() as $item) {
            if ($item->id === $itemId) {
                return $item;
            }
        }

        return null;
    }

    /** Times are tracked to the second. */
    private static function now(): DateTimeImmutable
    {
        $now = DateAndTimeService::getDateTimeImmutable();

        return $now->setTimestamp($now->getTimestamp());
    }
}
