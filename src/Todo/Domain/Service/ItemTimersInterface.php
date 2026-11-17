<?php

declare(strict_types=1);

namespace App\Todo\Domain\Service;

/**
 * The timers running on the list's items, as far as the todo list needs to know them: an item that is done or
 * deleted has no running timer.
 */
interface ItemTimersInterface
{
    /**
     * Stops the timer running on the item, if there is one.
     */
    public function stopTimerOf(string $itemId): void;
}
