<?php

declare(strict_types=1);

namespace App\Todo\Facade\SymfonyEvent;

/**
 * An item of the todo list was marked done.
 */
final readonly class ItemMarkedDoneSymfonyEvent
{
    public function __construct(
        public string $itemId
    ) {
    }
}
