<?php

declare(strict_types=1);

namespace App\Todo\Facade\SymfonyEvent;

use DateTimeImmutable;

/**
 * An item was deleted from the todo list.
 */
final readonly class ItemDeletedSymfonyEvent
{
    public function __construct(
        public string            $itemId,
        public DateTimeImmutable $deletedAt
    ) {
    }
}
