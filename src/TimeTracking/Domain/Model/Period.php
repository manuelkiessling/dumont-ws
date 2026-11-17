<?php

declare(strict_types=1);

namespace App\TimeTracking\Domain\Model;

use DateTimeImmutable;

/**
 * One period a timer ran: from the second it was started to the second it was stopped, or to now while it runs.
 */
final readonly class Period
{
    public function __construct(
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
        return new self($this->startedAt, $stoppedAt);
    }

    /**
     * The whole seconds of the period.
     */
    public function secondsAt(DateTimeImmutable $now): int
    {
        return max(0, ($this->stoppedAt ?? $now)->getTimestamp() - $this->startedAt->getTimestamp());
    }
}
