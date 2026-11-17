<?php

declare(strict_types=1);

namespace App\Todo\Domain\Service;

use App\Todo\Domain\Model\TodoItem;

/**
 * The port through which the domain keeps its items. Where and how they are kept is the infrastructure's business;
 * the domain only knows this interface.
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
