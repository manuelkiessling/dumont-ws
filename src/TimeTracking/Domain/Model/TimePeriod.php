<?php

declare(strict_types=1);

namespace App\TimeTracking\Domain\Model;

use DateTimeImmutable;

/**
 * One period in which the timer of an item ran, from the second it was started to the second it was stopped. A period
 * that is not stopped yet is the running timer.
 */
final readonly class TimePeriod
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
