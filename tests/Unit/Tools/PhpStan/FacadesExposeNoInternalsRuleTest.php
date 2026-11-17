<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tools\PhpStan;

use PHPUnit\Framework\TestCase;

final class FacadesExposeNoInternalsRuleTest extends TestCase
{
    use RunsPhpStan;

    private const string CONFIGURATION = <<<'NEON'
        rules:
            - App\Tools\PhpStan\Rules\FacadesExposeNoInternalsRule

        NEON;
    private const string WHY = ' A facade passes and returns DTOs, value objects, enums and primitives.';

    public function testReportsWhatAFacadeExposesOfALayerOrAModelAndNothingItKeepsToItself(): void
    {
        $this->assertSame(
            [
                'AlphaFacade.php:12 The property $exposed of facade App\Alpha\Facade\AlphaFacade is App\Alpha\Domain\Model\Thing, a class of the Domain layer.' . self::WHY,
                'AlphaFacadeInterface.php:19 The return type of entity() of facade App\Alpha\Facade\AlphaFacadeInterface is App\Alpha\Domain\Model\Thing, a class of the Domain layer.' . self::WHY,
                'AlphaFacadeInterface.php:21 The parameter $thing of store() of facade App\Alpha\Facade\AlphaFacadeInterface is App\Alpha\Domain\Model\Thing, a class of the Domain layer.' . self::WHY,
                'AlphaFacadeInterface.php:26 The return type of entities() of facade App\Alpha\Facade\AlphaFacadeInterface is App\Alpha\Domain\Model\Thing, a class of the Domain layer.' . self::WHY,
                'AlphaFacadeInterface.php:28 The return type of record() of facade App\Alpha\Facade\AlphaFacadeInterface is App\Common\Domain\Model\CommonRecord, a model of business state.' . self::WHY,
                'AlphaFacadeInterface.php:30 The return type of service() of facade App\Alpha\Facade\AlphaFacadeInterface is App\Alpha\Domain\Service\ThingService, a class of the Domain layer.' . self::WHY,
                'ThingDto.php:17 The property $thing of facade App\Alpha\Facade\Dto\ThingDto is App\Alpha\Domain\Model\Thing, a class of the Domain layer.' . self::WHY,
            ],
            $this->phpStanErrors(
                self::CONFIGURATION,
                dirname(__DIR__, 3) . '/Fixtures/PhpStan/FacadesExposeNoInternals'
            )
        );
    }
}
