<?php

declare(strict_types=1);

namespace App\Tools\PhpStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException;

/**
 * Models (App\<Vertical>\Domain\Model) decide only from what they are given: the domain service takes the current
 * time, chooses new IDs and reads the facts, and passes them in. This rule reports, inside a model, every read of the
 * clock, every draw of randomness, and every call of a domain service or of a facade's interface.
 *
 * @implements Rule<CallLike>
 */
final readonly class PureDomainCodeRule implements Rule
{
    private const string PURE_DOMAIN_CODE = '/\AApp\\\\(?!Tests\\\\|Tools\\\\)[^\\\\]+\\\\Domain\\\\(Model)\\\\/';
    private const string DOMAIN_SERVICE   = '/\AApp\\\\[^\\\\]+\\\\Domain\\\\Service\\\\/';
    private const string FACADE           = '/\AApp\\\\[^\\\\]+\\\\Facade\\\\/';

    private NonDeterministicCalls $nonDeterministicCalls;

    public function __construct(private ReflectionProvider $reflectionProvider)
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
        if ($class === null || 1 !== preg_match(self::PURE_DOMAIN_CODE, $class->getName(), $matches)) {
            return [];
        }

        $call = $this->forbiddenCall($node, $scope);
        if ($call === null) {
            return [];
        }

        [$what, $why] = $call;

        return [
            RuleErrorBuilder::message(sprintf(
                '%s, of Domain\%s, %s: %s.',
                $class->getName(),
                $matches[1],
                $what,
                $why
            ))->identifier('pureDomainCode')->build(),
        ];
    }

    /**
     * @return array{string, string}|null what the call is, and why pure domain code must not make it
     */
    private function forbiddenCall(CallLike $node, Scope $scope): ?array
    {
        $readOfTheClock = $this->nonDeterministicCalls->readOfTheClock($node, $scope);
        if ($readOfTheClock !== null) {
            return [$readOfTheClock, 'its caller takes the current time, once, and passes it in'];
        }

        $drawOfRandomness = $this->nonDeterministicCalls->drawOfRandomness($node, $scope);
        if ($drawOfRandomness !== null) {
            return [$drawOfRandomness, 'its caller chooses new IDs and passes them in'];
        }

        $calledClasses = match (true) {
            $node instanceof MethodCall, $node instanceof NullsafeMethodCall => $scope->getType($node->var)->getObjectClassNames(),
            $node instanceof StaticCall && $node->class instanceof Name      => [$scope->resolveName($node->class)],
            default                                                          => [],
        };
        foreach ($calledClasses as $className) {
            if (1 === preg_match(self::DOMAIN_SERVICE, $className)) {
                return ["calls the domain service {$className}", 'its caller reads the facts and passes them in'];
            }
            if (1 === preg_match(self::FACADE, $className)
                && $this->reflectionProvider->hasClass($className)
                && $this->reflectionProvider->getClass($className)->isInterface()
            ) {
                return ["calls the facade {$className}", 'its caller reads the facts and passes them in'];
            }
        }

        return null;
    }
}
