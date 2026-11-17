<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tools\PhpStan;

use PHPUnit\Framework\TestCase;

/**
 * In a vertical's Domain and Facade, what is named a kind, a type, a status or a state is no string: a parameter, a
 * property, the return of a method that reads one. A kind that is an enum is not reported, nor a string under another
 * name, nor a method that answers a kind in words, nor a string kind where the outside defines the value, in
 * Infrastructure and Presentation, nor anything outside the verticals.
 */
final class ClosedSetsAreEnumsRuleTest extends TestCase
{
    use RunsPhpStan;

    private const string CONFIGURATION = <<<'NEON'
        rules:
            - App\Tools\PhpStan\Rules\ClosedSetsAreEnumsRule

        NEON;
    private const string WHY = ' A kind, a type, a status and a state are one of a closed set: give the set an enum and pass its cases.';

    public function testReportsEveryStringThatIsNamedAKindATypeAStatusOrAStateInDomainAndFacadeAndNothingElse(): void
    {
        $this->assertSame(
            [
                'AlphaFacadeInterface.php:14 The parameter $subjectKind of App\Alpha\Facade\AlphaFacadeInterface::thingsAbout() is a string.' . self::WHY,
                'AlphaFacadeInterface.php:18 App\Alpha\Facade\AlphaFacadeInterface::kind() returns a string.' . self::WHY,
                'Thing.php:9 The property $kind of App\Alpha\Domain\Entity\Thing is a string.' . self::WHY,
                'Thing.php:11 The property $holderKind of App\Alpha\Domain\Entity\Thing is a string.' . self::WHY,
                'Thing.php:13 App\Alpha\Domain\Entity\Thing::getKind() returns a string.' . self::WHY,
                'ThingDto.php:13 The parameter $subjectKind of App\Alpha\Facade\Dto\ThingDto::__construct() is a string.' . self::WHY,
                'ThingDto.php:15 The parameter $contentType of App\Alpha\Facade\Dto\ThingDto::__construct() is a string.' . self::WHY,
                'ThingDto.php:17 The parameter $status of App\Alpha\Facade\Dto\ThingDto::__construct() is a string.' . self::WHY,
                'ThingDto.php:18 The parameter $sendingState of App\Alpha\Facade\Dto\ThingDto::__construct() is a string.' . self::WHY,
            ],
            $this->phpStanErrors(self::CONFIGURATION, dirname(__DIR__, 3) . '/Fixtures/PhpStan/ClosedSetsAreEnums')
        );
    }
}
