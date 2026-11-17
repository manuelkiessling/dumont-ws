<?php

declare(strict_types=1);

namespace App\Todo\Domain\Service;

use App\Todo\Domain\Model\TodoItem;

/**
 * Keeps the todo list's items.
 */
interface TodoItemStoreInterface
{
    /**
     * @return list<TodoItem> in the order they were added
     */
    public function load(): array;

    /**
     * @param list<TodoItem> $items
     */
    public function store(array $items): void;
}
