<?php

declare(strict_types=1);

namespace App\Todo\Facade\SymfonyEvent;

/**
 * An item of the todo list was marked done.
 */
final readonly class TodoItemDoneSymfonyEvent
{
    public function __construct(
        public string $itemId
    ) {
    }
}
