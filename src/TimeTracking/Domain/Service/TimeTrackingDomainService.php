<?php

declare(strict_types=1);

namespace App\TimeTracking\Domain\Service;

use App\Common\Domain\Service\DateAndTimeService;
use App\TimeTracking\Domain\Exception\ItemIsDoneException;
use App\TimeTracking\Domain\Exception\ItemNotOnTheListException;
use App\TimeTracking\Domain\Model\ItemTime;
use App\TimeTracking\Domain\Model\TrackedItem;
use App\Todo\Facade\Dto\TodoItemDto;
use App\Todo\Facade\TodoListFacadeInterface;

/**
 * The time tracking's use cases: read the items with their time, start a timer, stop it. Only one timer runs at a
 * time, and none on an item that is done. Every rule of the time tracking is decided here.
 */
final readonly class TimeTrackingDomainService
{
    public function __construct(
        private TimeRecordStoreInterface $store,
        private TodoListFacadeInterface  $todoList
    ) {
    }

    /**
     * The items of the todo list in its order, then the deleted items that have time.
     *
     * @return list<TrackedItem>
     */
    public function trackedItems(): array
    {
        $times = $this->timesById($this->store->load());

        $tracked = [];
        foreach ($this->todoList->items() as $item) {
            $tracked[] = new TrackedItem(
                $item->id,
                $item->text,
                $item->isDone,
                false,
                $times[$item->id] ?? null
            );
            unset($times[$item->id]);
        }
        foreach ($times as $time) {
            $tracked[] = new TrackedItem(
                $time->itemId,
                $time->text,
                false,
                true,
                $time
            );
        }

        return $tracked;
    }

    /**
     * @throws ItemNotOnTheListException
     * @throws ItemIsDoneException
     */
    public function startTimer(string $itemId): void
    {
        $item = $this->itemOnTheList($itemId);
        if ($item->isDone) {
            throw ItemIsDoneException::withId($itemId);
        }

        $now   = DateAndTimeService::getDateTimeImmutable();
        $times = $this->timesById($this->store->load());

        $times = array_map(
            static fn (ItemTime $time): ItemTime => $time->itemId === $itemId ? $time : $time->stoppedAt($now),
            $times
        );

        $times[$itemId] = ($times[$itemId] ?? new ItemTime($itemId, $item->text))->startedAt($now, $item->text);

        $this->store->store(array_values($times));
    }

    /**
     * Stopping a timer that does not run changes nothing and succeeds.
     */
    public function stopTimer(string $itemId): void
    {
        $now = DateAndTimeService::getDateTimeImmutable();

        $this->store->store(array_map(
            static fn (ItemTime $time): ItemTime => $time->itemId === $itemId ? $time->stoppedAt($now) : $time,
            $this->store->load()
        ));
    }

    /**
     * @param list<ItemTime> $times
     *
     * @return array<string, ItemTime>
     */
    private function timesById(array $times): array
    {
        $byId = [];
        foreach ($times as $time) {
            $byId[$time->itemId] = $time;
        }

        return $byId;
    }

    /**
     * @throws ItemNotOnTheListException
     */
    private function itemOnTheList(string $itemId): TodoItemDto
    {
        foreach ($this->todoList->items() as $item) {
            if ($item->id === $itemId) {
                return $item;
            }
        }

        throw ItemNotOnTheListException::withId($itemId);
    }
}
