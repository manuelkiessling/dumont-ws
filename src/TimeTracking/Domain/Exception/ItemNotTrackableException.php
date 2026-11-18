<?php

declare(strict_types=1);

namespace App\TimeTracking\Domain\Exception;

final class ItemNotTrackableException extends TimeTrackingRefusedException
{
    public static function create(): self
    {
        return new self('This item is no longer on your list.');
    }
}
