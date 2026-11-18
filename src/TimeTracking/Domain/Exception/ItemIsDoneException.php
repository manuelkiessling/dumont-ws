<?php

declare(strict_types=1);

namespace App\TimeTracking\Domain\Exception;

final class ItemIsDoneException extends TimeTrackingRefusedException
{
    public static function withId(string $itemId): self
    {
        return new self('This item is done, so no timer can be started on it.');
    }
}
