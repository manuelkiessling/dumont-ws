<?php

declare(strict_types=1);

namespace App\TimeTracking\Domain\Model;

use DateTimeImmutable;

/**
 * Time spent on one todo item: from when its timer was started until it was stopped, or until now while it runs.
 */
final readonly class TimeEntry
{
    public function __construct(
        public string             $id,
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
        return new self($this->id, $this->itemId, $this->startedAt, $stoppedAt);
    }

    public function seconds(DateTimeImmutable $now): int
    {
        return max(0, ($this->stoppedAt ?? $now)->getTimestamp() - $this->startedAt->getTimestamp());
    }
}
