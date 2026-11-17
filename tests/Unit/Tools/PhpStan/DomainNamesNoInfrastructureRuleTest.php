<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tools\PhpStan;

use PHPUnit\Framework\TestCase;

final class DomainNamesNoInfrastructureRuleTest extends TestCase
{
    use RunsPhpStan;

    private const string CONFIGURATION = <<<'NEON'
        rules:
            - App\Tools\PhpStan\Rules\DomainNamesNoInfrastructureRule

        NEON;

    public function testReportsEveryUseOfAnInfrastructureClassInDomainCodeButNotItsImportsOrOtherLayers(): void
    {
        $message = 'which lies under Infrastructure. The domain reaches outside it through a port under Domain that an adapter implements, and speaks in types under Domain.';

        $this->assertSame(
            [
                "AlphaDomainService.php:14 Domain code names App\\Alpha\\Infrastructure\\AlphaFileStore, {$message}",
                "AlphaDomainService.php:20 Domain code names App\\Alpha\\Infrastructure\\AlphaAdapter, {$message}",
                "AlphaDomainService.php:23 Domain code names App\\Alpha\\Infrastructure\\AlphaFileStore, {$message}",
            ],
            $this->phpStanErrors(
                self::CONFIGURATION,
                dirname(__DIR__, 3) . '/Fixtures/PhpStan/DomainNamesNoInfrastructure'
            )
        );
    }
}
