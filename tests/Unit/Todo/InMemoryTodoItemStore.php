<?php

declare(strict_types=1);

namespace App\Tests\Unit\Todo;

use App\Todo\Domain\Model\TodoItem;
use App\Todo\Domain\Service\TodoItemStoreInterface;

final class InMemoryTodoItemStore implements TodoItemStoreInterface
{
    /** @var list<TodoItem> */
    private array $items = [];

    public function load(): array
    {
        return $this->items;
    }

    public function store(array $items): void
    {
        $this->items = $items;
    }
}
