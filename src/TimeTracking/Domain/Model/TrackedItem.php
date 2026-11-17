<?php

declare(strict_types=1);

namespace App\TimeTracking\Domain\Model;

use App\Todo\Domain\Model\TodoItem;
use DateTimeImmutable;

/**
 * An item of the todo list with the time spent on it.
 */
final readonly class TrackedItem
{
    public function __construct(
        public TodoItem           $item,
        public int                $spentSeconds,
        public ?DateTimeImmutable $runningSince
    ) {
    }

    public function isRunning(): bool
    {
        return $this->runningSince !== null;
    }
}
