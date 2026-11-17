<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tools\PhpStan;

use PHPUnit\Framework\TestCase;

/**
 * `new DateTimeImmutable()` and its kin are reported, and code that asks DateAndTimeService is not.
 */
final class NoDirectDateTimeUsageRuleTest extends TestCase
{
    use RunsPhpStan;

    private const string CONFIGURATION = <<<'NEON'
        rules:
            - App\Tools\PhpStan\Rules\NoDirectDateTimeUsageRule

        NEON;

    public function testReportsEveryDirectUseOfTheClockAndNothingInCodeThatAsksTheService(): void
    {
        $this->assertSame(
            ['BadUsage.php:14 Direct usage of DateTimeImmutable is not allowed. Use DateAndTimeService::getDateTimeImmutable() instead.'],
            $this->phpStanErrors(self::CONFIGURATION, dirname(__DIR__, 3) . '/Fixtures/PhpStan/NoDirectDateTimeUsage')
        );
    }
}
