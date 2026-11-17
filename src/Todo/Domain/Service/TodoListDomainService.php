<?php

declare(strict_types=1);

namespace App\Todo\Domain\Service;

use App\Common\Domain\Service\DateAndTimeService;
use App\Todo\Domain\Exception\InvalidItemTextException;
use App\Todo\Domain\Exception\ItemNotOnTheListException;
use App\Todo\Domain\Model\TodoItem;
use App\Todo\Facade\SymfonyEvent\TodoItemDeletedSymfonyEvent;
use App\Todo\Facade\SymfonyEvent\TodoItemDoneSymfonyEvent;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The todo list's use cases: read the list, add an item, mark one done, delete one. Marking done and deleting
 * tell the other verticals by an event. Every rule of the list is
 * decided here.
 */
final readonly class TodoListDomainService
{
    public const int MAX_TEXT_LENGTH = 200;

    public function __construct(
        private TodoItemStoreInterface   $store,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * @return list<TodoItem>
     */
    public function items(): array
    {
        return $this->store->load();
    }

    /**
     * @throws InvalidItemTextException
     */
    public function addItem(string $text): TodoItem
    {
        $text = mb_trim($text);

        if ($text === '') {
            throw InvalidItemTextException::empty();
        }
        if (preg_match('/[\r\n]/', $text) === 1) {
            throw InvalidItemTextException::notOneLine();
        }
        if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
            throw InvalidItemTextException::tooLong(self::MAX_TEXT_LENGTH);
        }

        $item  = new TodoItem(Uuid::v7()->toRfc4122(), $text, DateAndTimeService::getDateTimeImmutable());
        $items = $this->store->load();

        $items[] = $item;
        $this->store->store($items);

        return $item;
    }

    /**
     * Marking an item done that is done already changes nothing and succeeds, so that a double submit is harmless.
     *
     * @throws ItemNotOnTheListException
     */
    public function markItemDone(string $itemId): void
    {
        $items = $this->store->load();
        $index = $this->indexOf($items, $itemId);

        if ($items[$index]->isDone()) {
            return;
        }

        $doneAt = DateAndTimeService::getDateTimeImmutable();
        $this->store->store(array_map(
            static fn (TodoItem $item): TodoItem => $item->id === $itemId ? $item->markedDoneAt($doneAt) : $item,
            $items
        ));
        $this->eventDispatcher->dispatch(new TodoItemDoneSymfonyEvent($itemId));
    }

    /**
     * @throws ItemNotOnTheListException
     */
    public function deleteItem(string $itemId): void
    {
        $items = $this->store->load();
        $index = $this->indexOf($items, $itemId);

        array_splice($items, $index, 1);
        $this->store->store($items);
        $this->eventDispatcher->dispatch(new TodoItemDeletedSymfonyEvent($itemId));
    }

    /**
     * @param list<TodoItem> $items
     *
     * @throws ItemNotOnTheListException
     */
    private function indexOf(array $items, string $itemId): int
    {
        foreach ($items as $index => $item) {
            if ($item->id === $itemId) {
                return $index;
            }
        }

        throw ItemNotOnTheListException::withId($itemId);
    }
}
