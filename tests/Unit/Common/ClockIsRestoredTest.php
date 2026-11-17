<?php

declare(strict_types=1);

namespace App\Tests\Unit\Common;

use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;

/**
 * Holds RestoresTheClockExtension: a test that freezes the clock leaves the real one behind.
 */
final class ClockIsRestoredTest extends TestCase
{
    public function testFreezesTheClock(): void
    {
        Clock::set(new MockClock('2001-02-03 04:05:06'));

        self::assertInstanceOf(MockClock::class, Clock::get());
    }

    #[Depends('testFreezesTheClock')]
    public function testStartsOnTheRealClock(): void
    {
        self::assertInstanceOf(NativeClock::class, Clock::get());
    }
}
