<?php

declare(strict_types=1);

namespace App\Tests\Unit\Common;

use App\Common\Domain\Service\DateAndTimeService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;

final class DateAndTimeServiceTest extends TestCase
{
    public function testTheTimeIsTheClocksInUtc(): void
    {
        Clock::set(new MockClock('2026-11-17 10:00:00', 'Europe/Berlin'));

        $now = DateAndTimeService::getDateTimeImmutable();

        self::assertSame('2026-11-17 09:00:00', $now->format('Y-m-d H:i:s'));
        self::assertSame('UTC', $now->getTimezone()->getName());
    }

    public function testTheTimeMovesWithTheClock(): void
    {
        $clock = new MockClock('2026-11-17 09:00:00');
        Clock::set($clock);

        $clock->sleep(90);

        self::assertSame('2026-11-17 09:01:30', DateAndTimeService::getDateTimeImmutable()->format('Y-m-d H:i:s'));
    }

    public function testAModifierChangesTheTime(): void
    {
        Clock::set(new MockClock('2026-11-17 09:00:00'));

        self::assertSame('2026-11-17 10:00:00', DateAndTimeService::getDateTimeImmutable('+1 hour')->format('Y-m-d H:i:s'));
        self::assertSame('2026-11-17 00:00:00', DateAndTimeService::getDateTimeImmutable('today')->format('Y-m-d H:i:s'));
        self::assertSame(
            '2001-02-03 04:05:06',
            DateAndTimeService::getDateTimeImmutable('2001-02-03 04:05:06')->format('Y-m-d H:i:s')
        );
    }
}
