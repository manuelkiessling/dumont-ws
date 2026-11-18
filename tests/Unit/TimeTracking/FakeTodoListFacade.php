<?php

declare(strict_types=1);

namespace App\Tests\Unit\TimeTracking;

use App\Todo\Facade\Dto\TodoItemDto;
use App\Todo\Facade\TodoListFacadeInterface;

/**
 * A stand-in for the todo list, that the test changes as the todo list screen in another terminal would.
 */
final class FakeTodoListFacade implements TodoListFacadeInterface
{
    /** @var list<TodoItemDto> */
    private array $items = [];

    public function items(): array
    {
        return $this->items;
    }

    public function add(string $id, string $text): void
    {
        $this->items[] = new TodoItemDto($id, $text, false);
    }

    public function markDone(string $id): void
    {
        $this->items = array_map(
            static fn (TodoItemDto $item): TodoItemDto => $item->id === $id ? new TodoItemDto(
                $id,
                $item->text,
                true
            ) : $item,
            $this->items
        );
    }

    public function delete(string $id): void
    {
        $this->items = array_values(array_filter(
            $this->items,
            static fn (TodoItemDto $item): bool => $item->id !== $id
        ));
    }
}
