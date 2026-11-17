<?php

declare(strict_types=1);

namespace App\Tests\Unit\Todo;

use App\Todo\Domain\Service\ItemTimersInterface;

final class RecordingItemTimers implements ItemTimersInterface
{
    /** @var list<string> */
    public array $stopped = [];

    public function stopTimerOf(string $itemId): void
    {
        $this->stopped[] = $itemId;
    }
}
