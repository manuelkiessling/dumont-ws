<?php

declare(strict_types=1);

namespace App\Todo\Facade;

use App\Todo\Domain\Model\TodoItem;
use App\Todo\Domain\Service\TodoListDomainService;
use App\Todo\Facade\Dto\TodoItemDto;

final readonly class TodoItemsFacade implements TodoItemsInterface
{
    public function __construct(
        private TodoListDomainService $todoList
    ) {
    }

    public function items(): array
    {
        return array_map($this->toDto(...), $this->todoList->items());
    }

    public function itemById(string $itemId): ?TodoItemDto
    {
        foreach ($this->todoList->items() as $item) {
            if ($item->id === $itemId) {
                return $this->toDto($item);
            }
        }

        return null;
    }

    private function toDto(TodoItem $item): TodoItemDto
    {
        return new TodoItemDto($item->id, $item->text, $item->isDone());
    }
}
