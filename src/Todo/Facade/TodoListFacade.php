<?php

declare(strict_types=1);

namespace App\Todo\Facade;

use App\Todo\Domain\Model\TodoItem;
use App\Todo\Domain\Service\TodoListDomainService;
use App\Todo\Facade\Dto\TodoItemDto;

final readonly class TodoListFacade implements TodoListFacadeInterface
{
    public function __construct(
        private TodoListDomainService $todoList
    ) {
    }

    public function items(): array
    {
        return array_map(
            static fn (TodoItem $item): TodoItemDto => new TodoItemDto($item->id, $item->text, $item->isDone()),
            $this->todoList->items()
        );
    }
}
