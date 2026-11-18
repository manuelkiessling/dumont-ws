<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tools\PhpStan;

use PHPUnit\Framework\TestCase;

/**
 * A library's methods and classes marked @internal are reported where the application uses them, its public ones and
 * the application's own @internal ones are not.
 */
final class NoInternalApiOfLibrariesRuleTest extends TestCase
{
    use RunsPhpStan;

    private const string CONFIGURATION = <<<'NEON'
        rules:
            - App\Tools\PhpStan\Rules\NoInternalApiOfLibrariesRule

        NEON;
    private const string WHY = ' is marked @internal by its library: the application uses only a library\'s public API.';

    public function testReportsTheInternalApiOfALibraryAndNothingElse(): void
    {
        $this->assertSame(
            [
                'Screen.php:20 Library\Scheduler::scheduleInterval()' . self::WHY,
                'Screen.php:21 Library\Scheduler::reset()' . self::WHY,
                'Screen.php:22 The class Library\Ticker' . self::WHY,
            ],
            $this->phpStanErrors(
                self::CONFIGURATION,
                dirname(__DIR__, 3) . '/Fixtures/PhpStan/NoInternalApiOfLibraries'
            )
        );
    }
}
