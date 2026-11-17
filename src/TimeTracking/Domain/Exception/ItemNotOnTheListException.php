<?php

declare(strict_types=1);

namespace App\TimeTracking\Domain\Exception;

final class ItemNotOnTheListException extends TimeTrackingRefusedException
{
    public static function withId(string $itemId): self
    {
        return new self('This item is no longer on your list.');
    }
}
