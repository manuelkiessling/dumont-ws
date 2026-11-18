<?php

declare(strict_types=1);

namespace App\Tools\PhpStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException;

/**
 * New IDs, and every other draw of randomness, are made by a domain service (App\<Vertical>\Domain\Service), which
 * passes them in where they are needed: a screen, a store, a listener or a facade makes none of its own, so that what
 * the application makes anew is decided in one place per use case. Models are held by PureDomainCodeRule, and tests
 * make what they need.
 *
 * @implements Rule<CallLike>
 */
final readonly class RandomnessOnlyInDomainServicesRule implements Rule
{
    private const string APPLICATION = '/\AApp\\\\(?!Tests\\\\|Tools\\\\)[^\\\\]+\\\\/';
    private const string ALLOWED     = '/\AApp\\\\[^\\\\]+\\\\Domain\\\\(Service|Model)\\\\/';

    private NonDeterministicCalls $nonDeterministicCalls;

    public function __construct()
    {
        $this->nonDeterministicCalls = new NonDeterministicCalls();
    }

    public function getNodeType(): string
    {
        return CallLike::class;
    }

    /**
     * @return list<IdentifierRuleError>
     *
     * @throws ShouldNotHappenException
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $class = $scope->getClassReflection();
        if ($class === null
            || 1 !== preg_match(self::APPLICATION, $class->getName())
            || 1 === preg_match(self::ALLOWED, $class->getName())
        ) {
            return [];
        }

        $draw = $this->nonDeterministicCalls->drawOfRandomness($node, $scope);
        if ($draw === null) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                '%s %s: new IDs and other randomness are made by a domain service, which passes them in.',
                $class->getName(),
                $draw
            ))->identifier('randomnessOnlyInDomainServices')->build(),
        ];
    }
}
