<?php

declare(strict_types=1);

namespace App\Todo\Facade\SymfonyEvent;

/**
 * An item of the todo list was deleted.
 */
final readonly class TodoItemDeletedSymfonyEvent
{
    public function __construct(
        public string $itemId
    ) {
    }
}
