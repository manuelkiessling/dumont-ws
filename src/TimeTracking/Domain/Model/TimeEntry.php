<?php

declare(strict_types=1);

namespace App\TimeTracking\Domain\Model;

use DateTimeImmutable;

/**
 * One period a timer ran on an item: from when it was started to when it was stopped, or until now while it runs.
 * Times are kept to the second.
 */
final readonly class TimeEntry
{
    public function __construct(
        public string             $itemId,
        public DateTimeImmutable  $startedAt,
        public ?DateTimeImmutable $stoppedAt = null
    ) {
    }

    public function isRunning(): bool
    {
        return $this->stoppedAt === null;
    }

    public function stoppedAt(DateTimeImmutable $stoppedAt): self
    {
        return new self($this->itemId, $this->startedAt, $stoppedAt);
    }

    /**
     * The whole seconds the timer ran, up to the given time while it still runs.
     */
    public function secondsAt(DateTimeImmutable $now): int
    {
        return max(0, ($this->stoppedAt ?? $now)->getTimestamp() - $this->startedAt->getTimestamp());
    }
}
