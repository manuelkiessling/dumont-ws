<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tools\PhpStan;

use PHPUnit\Framework\TestCase;

/**
 * IDs and randomness made outside a domain service are reported; a domain service and a test make them freely.
 */
final class RandomnessOnlyInDomainServicesRuleTest extends TestCase
{
    use RunsPhpStan;

    private const string CONFIGURATION = <<<'NEON'
        rules:
            - App\Tools\PhpStan\Rules\RandomnessOnlyInDomainServicesRule

        NEON;
    private const string WHY = ': new IDs and other randomness are made by a domain service, which passes them in.';

    public function testReportsRandomnessOutsideDomainServicesAndTests(): void
    {
        $this->assertSame(
            [
                'Things.php:24 App\Alpha\Infrastructure\Storage\ThingStore generates an identifier with Symfony\Component\Uid\Uuid::v7()' . self::WHY,
                'Things.php:29 App\Alpha\Infrastructure\Storage\ThingStore calls random_int()' . self::WHY,
                'Things.php:41 App\Alpha\Presentation\Tui\ThingScreen generates an identifier with new Symfony\Component\Uid\UuidV4()' . self::WHY,
            ],
            $this->phpStanErrors(
                self::CONFIGURATION,
                dirname(__DIR__, 3) . '/Fixtures/PhpStan/RandomnessOnlyInDomainServices'
            )
        );
    }
}
