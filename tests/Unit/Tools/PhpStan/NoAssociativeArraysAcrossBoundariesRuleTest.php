<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tools\PhpStan;

use PHPUnit\Framework\TestCase;

/**
 * An associative or nested array in a public signature is reported, on both sides of an application's own contract;
 * a simple list, and a signature a vendor's contract dictates, are not.
 */
final class NoAssociativeArraysAcrossBoundariesRuleTest extends TestCase
{
    use RunsPhpStan;

    private const string CONFIGURATION = <<<'NEON'
        rules:
            - App\Tools\PhpStan\Rules\NoAssociativeArraysAcrossBoundariesRule

        NEON;

    public function testReportsAssociativeAndNestedArraysAcrossAClassBoundaryAndNoSimpleListOrVendorSignature(): void
    {
        $this->assertSame(
            [
                self::reported(
                    'ImplementsAppContract.php:10',
                    'parameter $map of',
                    'AppContractWithAssociativeParam::setMap()'
                ),
                self::reported('ImplementsAppContract.php:16', 'parameter $map of', 'ImplementsAppContract::setMap()'),
                self::reported(
                    'PublicParamAssociative.php:10',
                    'parameter $map of',
                    'PublicParamAssociative::setMap()'
                ),
                self::reported(
                    'PublicParamListOfMixed.php:10',
                    'parameter $values of',
                    'PublicParamListOfMixed::setMixedValues()'
                ),
                self::reported('PublicReturnAssociative.php:10', 'return type of', 'PublicReturnAssociative::getMap()'),
                self::reported(
                    'PublicReturnListOfArrays.php:10',
                    'return type of',
                    'PublicReturnListOfArrays::windows()'
                ),
                self::reported(
                    'PublicReturnListOfMixed.php:10',
                    'return type of',
                    'PublicReturnListOfMixed::getMixedValues()'
                ),
            ],
            $this->phpStanErrors(
                self::CONFIGURATION,
                dirname(__DIR__, 3) . '/Fixtures/PhpStan/NoAssociativeArraysAcrossBoundaries'
            )
        );
    }

    private static function reported(string $where, string $what, string $method): string
    {
        return "{$where} Only simple lists (list<T>), or non-array T parameters are allowed across class boundaries; {$what} App\\Tests\\Fixtures\\PhpStan\\NoAssociativeArraysAcrossBoundaries\\Bad\\{$method} uses an associative/complex array type, or is incorrectly defined as T[] or array<T> instead of list<T>. Define a dedicated DTO instead if this is indeed a complex/associative array, or change the type annotation to list<T>.";
    }
}
