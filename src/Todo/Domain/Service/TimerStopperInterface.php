<?php

declare(strict_types=1);

namespace App\Todo\Domain\Service;

interface TimerStopperInterface
{
    /** Stops the timer of the item, if one is running on it. */
    public function stopTimerOf(string $itemId): void;
}
