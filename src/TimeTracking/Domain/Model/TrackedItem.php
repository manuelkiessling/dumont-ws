<?php

declare(strict_types=1);

namespace App\TimeTracking\Domain\Model;

use DateTimeImmutable;

/**
 * An item of the todo list with the time spent on it: the sum of all periods its timer ran.
 */
final readonly class TrackedItem
{
    public function __construct(
        public string             $itemId,
        public string             $text,
        public bool               $done,
        public int                $seconds,
        public ?DateTimeImmutable $runningSince = null
    ) {
    }

    public function isRunning(): bool
    {
        return $this->runningSince !== null;
    }
}
