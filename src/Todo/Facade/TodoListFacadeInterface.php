<?php

declare(strict_types=1);

namespace App\Todo\Facade;

use App\Todo\Facade\Dto\TodoItemDto;

/**
 * What other verticals may ask of the todo list.
 */
interface TodoListFacadeInterface
{
    /**
     * @return list<TodoItemDto> in the order they were added
     */
    public function items(): array;
}
