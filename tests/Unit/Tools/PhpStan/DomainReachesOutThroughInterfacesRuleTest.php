<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tools\PhpStan;

use PHPUnit\Framework\TestCase;

use function dirname;

/**
 * A constructor of a class under Domain that takes a concrete class from outside Domain is reported: a domain service's
 * in a `Domain\Service` namespace and under the name `…DomainService` alike, and a console command's under `Domain`;
 * one that takes interfaces, siblings under Domain, values (an enum, a date, a facade's DTO) and scalars is not, and a
 * class outside Domain takes what it likes.
 */
final class DomainReachesOutThroughInterfacesRuleTest extends TestCase
{
    use RunsPhpStan;

    private const string CONFIGURATION = <<<'NEON'
        rules:
            - App\Tools\PhpStan\Rules\DomainReachesOutThroughInterfacesRule

        NEON;

    public function testReportsAConcreteClassFromOutsideDomainAndNothingElse(): void
    {
        $this->assertSame(
            [
                'BadCommand.php:12 A class under Domain reaches outside Domain through interfaces only: parameter $adapter of App\Tests\Fixtures\PhpStan\DomainReachesOutThroughInterfaces\Domain\Command\BadCommand is typed to the concrete class App\Tests\Fixtures\PhpStan\DomainReachesOutThroughInterfaces\Infrastructure\ConcreteAdapter, which is no value.',
                'BadDomainService.php:13 A class under Domain reaches outside Domain through interfaces only: parameter $adapter of App\Tests\Fixtures\PhpStan\DomainReachesOutThroughInterfaces\Domain\Service\BadDomainService is typed to the concrete class App\Tests\Fixtures\PhpStan\DomainReachesOutThroughInterfaces\Infrastructure\ConcreteAdapter, which is no value.',
                'NamedDomainService.php:11 A class under Domain reaches outside Domain through interfaces only: parameter $adapter of App\Tests\Fixtures\PhpStan\DomainReachesOutThroughInterfaces\Application\NamedDomainService is typed to the concrete class App\Tests\Fixtures\PhpStan\DomainReachesOutThroughInterfaces\Infrastructure\ConcreteAdapter, which is no value.',
            ],
            $this->phpStanErrors(
                self::CONFIGURATION,
                dirname(__DIR__, 3) . '/Fixtures/PhpStan/DomainReachesOutThroughInterfaces'
            )
        );
    }
}
