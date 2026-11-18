<?php

declare(strict_types=1);

namespace App\TimeTracking\Domain\Model;

use DateTimeImmutable;

/**
 * An item of the todo list with the time spent on it: the sum of the periods its timer ran.
 */
final readonly class TrackedItem
{
    public function __construct(
        public string             $itemId,
        public string             $text,
        public bool               $isDone,
        public int                $secondsSpent,
        public ?DateTimeImmutable $runningSince = null
    ) {
    }

    public function isRunning(): bool
    {
        return $this->runningSince !== null;
    }
}
