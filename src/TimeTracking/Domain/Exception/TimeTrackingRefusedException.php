<?php

declare(strict_types=1);

namespace App\TimeTracking\Domain\Exception;

use RuntimeException;

class TimeTrackingRefusedException extends RuntimeException
{
    public static function itemIsDone(): self
    {
        return new self('This item is done, so no timer can be started on it.');
    }

    public static function itemNotOnTheList(): self
    {
        return new self('This item is no longer on your todo list, so no timer can be started on it.');
    }
}
