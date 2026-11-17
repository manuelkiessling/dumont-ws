<?php

declare(strict_types=1);

namespace App\Todo\Facade\SymfonyEvent;

/**
 * An item was deleted from the todo list.
 */
final readonly class TodoItemDeletedSymfonyEvent
{
    public function __construct(
        public string $itemId
    ) {
    }
}
