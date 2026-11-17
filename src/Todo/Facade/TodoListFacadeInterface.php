<?php

declare(strict_types=1);

namespace App\Todo\Facade;

use App\Todo\Facade\Dto\TodoItemDto;

/**
 * What the todo list tells other verticals: the items on the list now.
 */
interface TodoListFacadeInterface
{
    /**
     * @return list<TodoItemDto> in the order they were added
     */
    public function items(): array;
}
