<?php

declare(strict_types=1);

namespace App\TimeTracking\Domain\Model;

use DateTimeImmutable;

/**
 * The time spent on one todo item: the periods its timer ran, and since when it runs, if it does.
 * All instants are whole seconds.
 */
final readonly class TimeRecord
{
    /**
     * @param list<array{startedAt: DateTimeImmutable, stoppedAt: DateTimeImmutable}> $periods
     */
    public function __construct(
        public string             $itemId,
        public string             $itemText,
        public array              $periods = [],
        public ?DateTimeImmutable $runningSince = null
    ) {
    }

    public function isRunning(): bool
    {
        return $this->runningSince !== null;
    }

    public function started(DateTimeImmutable $now, string $itemText): self
    {
        return $this->isRunning() ? $this : new self($this->itemId, $itemText, $this->periods, $now);
    }

    public function stopped(DateTimeImmutable $now): self
    {
        if ($this->runningSince === null) {
            return $this;
        }

        $periods   = $this->periods;
        $periods[] = ['startedAt' => $this->runningSince, 'stoppedAt' => $now];

        return new self($this->itemId, $this->itemText, $periods, null);
    }

    public function secondsSpent(DateTimeImmutable $now): int
    {
        $seconds = 0;
        foreach ($this->periods as $period) {
            $seconds += $period['stoppedAt']->getTimestamp() - $period['startedAt']->getTimestamp();
        }
        if ($this->runningSince !== null) {
            $seconds += max(0, $now->getTimestamp() - $this->runningSince->getTimestamp());
        }

        return $seconds;
    }
}
