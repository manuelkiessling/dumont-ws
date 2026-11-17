<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tools\PhpStan;

use PHPUnit\Framework\TestCase;

use function dirname;

/**
 * A facade DTO made as a copy, field by field, of a DTO of its vertical that has the same fields is reported, in the
 * facade class and in a domain service alike; a facade DTO that leaves a field out, one made of an entity, one whose
 * field is worked on, one made of two things or with its fields in other places, and one read into directly are not.
 */
final class NoDtoBesideAFacadeDtoOfTheSameFieldsRuleTest extends TestCase
{
    use RunsPhpStan;

    private const string CONFIGURATION = <<<'NEON'
        rules:
            - App\Tools\PhpStan\Rules\NoDtoBesideAFacadeDtoOfTheSameFieldsRule

        NEON;
    private const string MESSAGE = 'App\Tests\Fixtures\PhpStan\NoDtoBesideAFacadeDtoOfTheSameFields\Facade\Dto\ThingDto is copied field by field from App\Tests\Fixtures\PhpStan\NoDtoBesideAFacadeDtoOfTheSameFields\Domain\Dto\ThingDto, which has the same fields: the vertical reads into the facade\'s DTO and keeps none of its own beside it.';

    public function testReportsTheCopyOfADtoOfTheSameFieldsAndNothingElse(): void
    {
        $this->assertSame(
            [
                'CopyingFacade.php:17 ' . self::MESSAGE,
                'CopyingFacade.php:31 ' . self::MESSAGE,
                'CopyingService.php:15 ' . self::MESSAGE,
            ],
            $this->phpStanErrors(
                self::CONFIGURATION,
                dirname(__DIR__, 3) . '/Fixtures/PhpStan/NoDtoBesideAFacadeDtoOfTheSameFields'
            )
        );
    }
}
