<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tools\PhpStan;

use PHPUnit\Framework\TestCase;

use function dirname;

/**
 * A class behind a facade interface whose every method hands its parameters on to one and the same class is
 * reported; one that composes two classes, adapts a call or does work in a method is not, and neither is the class
 * that answers and implements the interface itself, or a class under Facade that stands behind no interface.
 */
final class FacadeClassesHaveWorkOfTheirOwnRuleTest extends TestCase
{
    use RunsPhpStan;

    private const string CONFIGURATION = <<<'NEON'
        rules:
            - App\Tools\PhpStan\Rules\FacadeClassesHaveWorkOfTheirOwnRule

        NEON;

    public function testReportsTheClassThatOnlyMirrorsAndNothingElse(): void
    {
        $this->assertSame(
            [
                'MirroringFacade.php:10 App\Tests\Fixtures\PhpStan\FacadeClassesHaveWorkOfTheirOwn\Facade\MirroringFacade only hands every call on to App\Tests\Fixtures\PhpStan\FacadeClassesHaveWorkOfTheirOwn\Domain\Service\ThingService, so it is no contract of its own: the class that answers implements the facade interface itself.',
            ],
            $this->phpStanErrors(
                self::CONFIGURATION,
                dirname(__DIR__, 3) . '/Fixtures/PhpStan/FacadeClassesHaveWorkOfTheirOwn'
            )
        );
    }
}
