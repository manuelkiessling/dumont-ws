<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tools\PhpStan;

use PHPUnit\Framework\TestCase;

final class PureDomainCodeRuleTest extends TestCase
{
    use RunsPhpStan;

    private const string CONFIGURATION = <<<'NEON'
        rules:
            - App\Tools\PhpStan\Rules\PureDomainCodeRule

        NEON;
    private const string WHY        = '.';
    private const string CLOCK      = 'its caller takes the current time, once, and passes it in';
    private const string RANDOMNESS = 'its caller chooses new IDs and passes them in';
    private const string FACTS      = 'its caller reads the facts and passes them in';

    public function testReportsTheClockRandomnessAndServicesInModelsAndNowhereElse(): void
    {
        $this->assertSame(
            [
                'EntryRecorded.php:17 App\Alpha\Domain\Model\EntryRecorded, of Domain\Model, calls App\Common\Domain\Service\DateAndTimeService: ' . self::CLOCK . self::WHY,
                'Ledger.php:30 App\Alpha\Domain\Model\Ledger, of Domain\Model, calls App\Common\Domain\Service\DateAndTimeService: ' . self::CLOCK . self::WHY,
                'Ledger.php:31 App\Alpha\Domain\Model\Ledger, of Domain\Model, calls time(): ' . self::CLOCK . self::WHY,
                'Ledger.php:32 App\Alpha\Domain\Model\Ledger, of Domain\Model, generates an identifier with Symfony\Component\Uid\Uuid::v7(): ' . self::RANDOMNESS . self::WHY,
                'Ledger.php:33 App\Alpha\Domain\Model\Ledger, of Domain\Model, calls random_int(): ' . self::RANDOMNESS . self::WHY,
                'Ledger.php:34 App\Alpha\Domain\Model\Ledger, of Domain\Model, generates an identifier with new Symfony\Component\Uid\UuidV4(): ' . self::RANDOMNESS . self::WHY,
                'Ledger.php:35 App\Alpha\Domain\Model\Ledger, of Domain\Model, calls the facade App\Alpha\Facade\AlphaFacadeInterface: ' . self::FACTS . self::WHY,
                'ThingPolicy.php:25 App\Alpha\Domain\Model\ThingPolicy, of Domain\Model, calls shuffle(): ' . self::RANDOMNESS . self::WHY,
                'ThingPolicy.php:27 App\Alpha\Domain\Model\ThingPolicy, of Domain\Model, calls date(): ' . self::CLOCK . self::WHY,
                'ThingPolicy.php:27 App\Alpha\Domain\Model\ThingPolicy, of Domain\Model, calls the domain service App\Alpha\Domain\Service\ThingServiceInterface: ' . self::FACTS . self::WHY,
                'ThingPolicy.php:27 App\Alpha\Domain\Model\ThingPolicy, of Domain\Model, creates a Random\Randomizer: ' . self::RANDOMNESS . self::WHY,
            ],
            $this->phpStanErrors(self::CONFIGURATION, dirname(__DIR__, 3) . '/Fixtures/PhpStan/PureDomainCode')
        );
    }
}
