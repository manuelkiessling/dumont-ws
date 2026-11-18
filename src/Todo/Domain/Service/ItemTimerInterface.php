<?php

declare(strict_types=1);

namespace App\Todo\Domain\Service;

/**
 * What the todo list needs from time tracking: a timer must not keep running on an item that is done or deleted.
 */
interface ItemTimerInterface
{
    public function stopTimerOf(string $itemId): void;
}
