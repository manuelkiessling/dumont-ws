<?php

declare(strict_types=1);

namespace App\TimeTracking\Domain\Service;

use App\Common\Domain\Service\DateAndTimeService;
use App\TimeTracking\Domain\Exception\ItemIsDoneException;
use App\TimeTracking\Domain\Exception\ItemNotTrackableException;
use App\TimeTracking\Domain\Model\TimeRecord;
use App\TimeTracking\Domain\Model\TrackedItem;
use App\Todo\Domain\Model\TodoItem;
use App\Todo\Domain\Service\ItemTimerInterface;
use App\Todo\Domain\Service\TodoItemStoreInterface;
use DateTimeImmutable;

final readonly class TimeTrackingDomainService implements ItemTimerInterface
{
    public function __construct(
        private TimeRecordStoreInterface $records,
        private TodoItemStoreInterface   $todoItems
    ) {
    }

    /**
     * The items of the todo list with the time spent on each.
     *
     * @return list<TrackedItem>
     */
    public function items(): array
    {
        $now     = $this->now();
        $records = $this->recordsById($this->records->load());
        $items   = [];

        foreach ($this->todoItems->load() as $item) {
            $record  = $records[$item->id] ?? null;
            $items[] = new TrackedItem($item, $record?->secondsSpent($now) ?? 0, $record?->runningSince);
        }

        return $items;
    }

    /**
     * The time spent on an item, in seconds, also when the item has been deleted.
     */
    public function secondsSpentOn(string $itemId): int
    {
        $record = $this->recordsById($this->records->load())[$itemId] ?? null;

        return $record?->secondsSpent($this->now()) ?? 0;
    }

    /**
     * Starts the timer of the item and stops the timer running on any other item.
     */
    public function startTimer(string $itemId): void
    {
        $item = $this->findItem($itemId) ?? throw ItemNotTrackableException::create();
        if ($item->isDone()) {
            throw ItemIsDoneException::create();
        }

        $now     = $this->now();
        $records = $this->recordsById($this->records->load());
        foreach ($records as $id => $record) {
            if ($id !== $itemId) {
                $records[$id] = $record->stopped($now);
            }
        }
        $records[$itemId] = ($records[$itemId] ?? new TimeRecord($itemId, $item->text))->started($now, $item->text);

        $this->records->store(array_values($records));
    }

    /**
     * Stops the timer that is running, if any.
     */
    public function stopTimer(): void
    {
        $now     = $this->now();
        $records = array_map(static fn (TimeRecord $record): TimeRecord => $record->stopped($now), $this->records->load());

        $this->records->store($records);
    }

    public function stopTimerOf(string $itemId): void
    {
        $now     = $this->now();
        $records = $this->records->load();
        $changed = false;

        foreach ($records as $index => $record) {
            if ($record->itemId === $itemId && $record->isRunning()) {
                $records[$index] = $record->stopped($now);
                $changed         = true;
            }
        }

        if ($changed) {
            $this->records->store($records);
        }
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

    /**
     * @param list<TimeRecord> $records
     *
     * @return array<string, TimeRecord>
     */
    private function recordsById(array $records): array
    {
        $byId = [];
        foreach ($records as $record) {
            $byId[$record->itemId] = $record;
        }

        return $byId;
    }

    private function now(): DateTimeImmutable
    {
        $now = DateAndTimeService::getDateTimeImmutable();

        return $now->setTimestamp($now->getTimestamp());
    }
}
