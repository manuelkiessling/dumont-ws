<?php

declare(strict_types=1);

namespace App\TimeTracking\Domain\Model;

use DateTimeImmutable;

/**
 * The time spent on one item: the periods its timer ran. The text is the item's text when the timer was last started,
 * so that the time stays readable once the item is deleted.
 */
final readonly class ItemTime
{
    /**
     * @param list<Period> $periods in the order they started; only the last may be running
     */
    public function __construct(
        public string $itemId,
        public string $text,
        public array  $periods = []
    ) {
    }

    public function isRunning(): bool
    {
        return $this->runningSince() !== null;
    }

    public function runningSince(): ?DateTimeImmutable
    {
        $last = $this->periods === [] ? null : $this->periods[array_key_last($this->periods)];

        return $last !== null && $last->isRunning() ? $last->startedAt : null;
    }

    public function secondsAt(DateTimeImmutable $now): int
    {
        return array_sum(array_map(static fn (Period $period): int => $period->secondsAt($now), $this->periods));
    }

    /**
     * Starting a timer that runs already changes nothing.
     */
    public function startedAt(DateTimeImmutable $now, string $text): self
    {
        if ($this->isRunning()) {
            return $this;
        }

        return new self($this->itemId, $text, [...$this->periods, new Period($now)]);
    }

    /**
     * Stopping a timer that does not run changes nothing.
     */
    public function stoppedAt(DateTimeImmutable $now): self
    {
        if (!$this->isRunning()) {
            return $this;
        }

        $periods = array_map(
            static fn (Period $period): Period => $period->isRunning() ? $period->stoppedAt($now) : $period,
            $this->periods
        );

        return new self($this->itemId, $this->text, $periods);
    }
}
