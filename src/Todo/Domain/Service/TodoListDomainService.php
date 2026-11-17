<?php

declare(strict_types=1);

namespace App\Todo\Domain\Service;

use App\Common\Domain\Service\DateAndTimeService;
use App\Todo\Domain\Exception\InvalidItemTextException;
use App\Todo\Domain\Exception\ItemNotOnTheListException;
use App\Todo\Domain\Model\TodoItem;
use Symfony\Component\Uid\Uuid;

final readonly class TodoListDomainService
{
    public const int MAX_TEXT_LENGTH = 200;

    public function __construct(
        private TodoItemStoreInterface $store
    ) {
    }

    public function items(): array
    {
        return $this->store->load();
    }

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

    public function markItemDone(string $itemId): void
    {
        $items = $this->store->load();
        $index = $this->indexOf($items, $itemId);

        if ($items[$index]->isDone()) {
            return;
        }

        $items[$index] = $items[$index]->markedDoneAt(DateAndTimeService::getDateTimeImmutable());
        $this->store->store($items);
    }

    public function deleteItem(string $itemId): void
    {
        $items = $this->store->load();
        $index = $this->indexOf($items, $itemId);

        array_splice($items, $index, 1);
        $this->store->store($items);
    }

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
