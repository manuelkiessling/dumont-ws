<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tools\PhpStan;

use App\Tools\PhpStan\Rules\VerticalDependencyDirectionRule;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class VerticalDependencyDirectionRuleTest extends TestCase
{
    use RunsPhpStan;

    private const string CONFIGURATION = <<<'NEON'
        services:
            -
                class: App\Tools\PhpStan\Rules\VerticalDependencyDirectionRule
                arguments:
                    allowedDependencies:
                        - 'Alpha -> Beta'
                        - 'Beta ->'
                tags: [phpstan.rules.rule]

        NEON;

    public function testReportsEveryLineOnWhichAConstrainedVerticalNamesAVerticalItMustNotDependOn(): void
    {
        $this->assertSame(
            [
                'AlphaDomainService.php:9 Alpha must not depend on Gamma: it names App\Gamma\Facade\GammaDto. Besides Common, Alpha may depend on Beta only (the allowed dependencies are configured in phpstan.dist.neon).',
                'AlphaDomainService.php:23 Alpha must not depend on Gamma: it names App\Gamma\Facade\GammaDto. Besides Common, Alpha may depend on Beta only (the allowed dependencies are configured in phpstan.dist.neon).',
                'AlphaDomainService.php:25 Alpha must not depend on Gamma: it names App\Gamma\Facade\GammaDto. Besides Common, Alpha may depend on Beta only (the allowed dependencies are configured in phpstan.dist.neon).',
                'AlphaPresentation.php:7 Alpha must not depend on Gamma: it names App\Gamma\Facade\GammaDto. Besides Common, Alpha may depend on Beta only (the allowed dependencies are configured in phpstan.dist.neon).',
                'BetaUsingAlpha.php:11 Beta must not depend on Alpha: it names App\Alpha\Domain\AlphaDomainService. Besides Common, Beta may depend on no other vertical (the allowed dependencies are configured in phpstan.dist.neon).',
            ],
            $this->phpStanErrors(
                self::CONFIGURATION,
                dirname(__DIR__, 3) . '/Fixtures/PhpStan/VerticalDependencyDirection'
            )
        );
    }

    public function testPresentationPermissionDoesNotPermitTheDomainToDependBack(): void
    {
        $configuration = <<<'NEON'
            services:
                -
                    class: App\Tools\PhpStan\Rules\VerticalDependencyDirectionRule
                    arguments:
                        allowedDependencies:
                            - 'Alpha -> Beta'
                            - 'Beta ->'
                        presentationDependencies:
                            - 'Alpha -> Gamma'
                    tags: [phpstan.rules.rule]
            NEON;
        $errors = $this->phpStanErrors(
            $configuration,
            dirname(__DIR__, 3) . '/Fixtures/PhpStan/VerticalDependencyDirection'
        );
        self::assertCount(4, $errors);
        self::assertStringContainsString('AlphaDomainService.php:9', $errors[0]);
        self::assertStringContainsString('BetaUsingAlpha.php:11', $errors[3]);
    }

    public function testRefusesAllowedDependenciesThatFormACycle(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The allowed dependencies between verticals form a cycle: Alpha -> Beta -> Gamma -> Alpha.');

        new VerticalDependencyDirectionRule(['Alpha -> Beta', 'Beta -> Gamma', 'Gamma -> Alpha']);
    }

    public function testRefusesAMalformedEntry(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Not an allowed dependency, "<Vertical> -> <Vertical>, …": "Alpha: Beta".');

        new VerticalDependencyDirectionRule(['Alpha: Beta']);
    }

    public function testRefusesAVerticalConfiguredTwice(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The allowed dependencies of Alpha are configured twice.');

        new VerticalDependencyDirectionRule(['Alpha -> Beta', 'Alpha ->']);
    }
}
