<?php

declare(strict_types=1);

namespace App\TimeTracking\Domain\Model;

use DateTimeImmutable;

/** An item of the todo list (or one that was deleted from it) with the time spent on it. */
final readonly class TrackedItem
{
    public function __construct(
        public string             $id,
        public string             $text,
        public bool               $isDone,
        public bool               $isDeleted,
        public int                $seconds,
        public ?DateTimeImmutable $runningSince
    ) {
    }

    public function isRunning(): bool
    {
        return $this->runningSince !== null;
    }
}
