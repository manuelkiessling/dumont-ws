<?php

declare(strict_types=1);

namespace App\Common\Domain\Service;

use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Clock\Clock;

final readonly class DateAndTimeService
{
    public static function getDateTimeImmutable(string $modifier = 'now'): DateTimeImmutable
    {
        return Clock::get()->withTimeZone(new DateTimeZone('UTC'))->now()->modify($modifier);
    }
}
