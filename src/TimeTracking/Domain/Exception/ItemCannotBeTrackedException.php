<?php

declare(strict_types=1);

namespace App\TimeTracking\Domain\Exception;

final class ItemCannotBeTrackedException extends TimeTrackingRefusedException
{
    public static function notOnTheList(): self
    {
        return new self('This item is no longer on your todo list.');
    }

    public static function done(): self
    {
        return new self('This item is done; its time can no longer be tracked.');
    }
}
