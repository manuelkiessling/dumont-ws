<?php

declare(strict_types=1);

namespace App\TimeTracking\Domain\Model;

use App\Todo\Domain\Model\TodoItem;
use DateTimeImmutable;

final readonly class TrackedItem
{
    public function __construct(
        public TodoItem           $item,
        public int                $secondsSpent,
        public ?DateTimeImmutable $runningSince
    ) {
    }

    public function isRunning(): bool
    {
        return $this->runningSince !== null;
    }
}
