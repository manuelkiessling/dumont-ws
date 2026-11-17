<?php

declare(strict_types=1);

namespace App\TimeTracking\Domain\Model;

use DateTimeImmutable;

/**
 * An item with the time spent on it. An item that is not on the todo list any more is deleted, and still has its time.
 */
final readonly class TrackedItem
{
    public function __construct(
        public string    $itemId,
        public string    $text,
        public bool      $isDone,
        public bool      $isDeleted,
        public ?ItemTime $time
    ) {
    }

    public function isRunning(): bool
    {
        return $this->time?->isRunning() ?? false;
    }

    public function runningSince(): ?DateTimeImmutable
    {
        return $this->time?->runningSince();
    }

    public function secondsAt(DateTimeImmutable $now): int
    {
        return $this->time?->secondsAt($now) ?? 0;
    }
}
