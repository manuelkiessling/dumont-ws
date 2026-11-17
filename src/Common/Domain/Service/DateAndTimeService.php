<?php

declare(strict_types=1);

namespace App\Common\Domain\Service;

use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Clock\Clock;

/**
 * The one source of the current time. Code never creates the time itself with `new DateTimeImmutable()`; it asks
 * here, and a test sets the clock this reads with `Clock::set(new MockClock('2026-11-17 09:00:00'))`.
 */
final readonly class DateAndTimeService
{
    /**
     * The current time in UTC, changed by the modifier: 'now', '+1 hour', or a point in time like
     * '2026-11-17 09:00:00'.
     */
    public static function getDateTimeImmutable(string $modifier = 'now'): DateTimeImmutable
    {
        return Clock::get()->withTimeZone(new DateTimeZone('UTC'))->now()->modify($modifier);
    }
}
