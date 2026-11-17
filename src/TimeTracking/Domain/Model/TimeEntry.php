<?php

declare(strict_types=1);

namespace App\TimeTracking\Domain\Model;

use DateTimeImmutable;

/** One period during which the timer of an item ran. It is running as long as it has not been stopped. */
final readonly class TimeEntry
{
    public function __construct(
        public string             $itemId,
        public string             $itemText,
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
        return new self($this->itemId, $this->itemText, $this->startedAt, $stoppedAt);
    }

    public function secondsAt(DateTimeImmutable $now): int
    {
        return max(0, ($this->stoppedAt ?? $now)->getTimestamp() - $this->startedAt->getTimestamp());
    }
}
