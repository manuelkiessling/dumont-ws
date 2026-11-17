<?php

declare(strict_types=1);

namespace App\TimeTracking\Domain\Model;

use DateTimeImmutable;

/**
 * One period an item's timer ran: from starting it until stopping it, or until now while it is still running.
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
     * The seconds (with fraction) this period lasted, up to $now if it is still running.
     */
    public function seconds(DateTimeImmutable $now): float
    {
        $end = $this->stoppedAt ?? $now;

        return max(0.0, (float) $end->format('U.u') - (float) $this->startedAt->format('U.u'));
    }
}
