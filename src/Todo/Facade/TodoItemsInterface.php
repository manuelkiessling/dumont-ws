<?php

declare(strict_types=1);

namespace App\Todo\Facade;

use App\Todo\Facade\Dto\TodoItemDto;

/**
 * The todo list's items, for other verticals to read.
 */
interface TodoItemsInterface
{
    /**
     * @return list<TodoItemDto> in the order they were added
     */
    public function items(): array;

    public function itemById(string $itemId): ?TodoItemDto;
}
