<?php

declare(strict_types=1);

namespace App\Tests\Unit\TimeTracking;

use App\Todo\Facade\Dto\TodoItemDto;
use App\Todo\Facade\TodoItemsInterface;

/**
 * Stands in for the todo list: holds whatever items a test gives it.
 */
final class FakeTodoItems implements TodoItemsInterface
{
    /** @var list<TodoItemDto> */
    private array $items = [];

    public function add(string $itemId, string $text, bool $isDone = false): void
    {
        $this->items[] = new TodoItemDto($itemId, $text, $isDone);
    }

    public function remove(string $itemId): void
    {
        $this->items = array_values(array_filter($this->items, static fn (TodoItemDto $item) => $item->itemId !== $itemId));
    }

    public function items(): array
    {
        return $this->items;
    }

    public function itemById(string $itemId): ?TodoItemDto
    {
        foreach ($this->items as $item) {
            if ($item->itemId === $itemId) {
                return $item;
            }
        }

        return null;
    }
}
