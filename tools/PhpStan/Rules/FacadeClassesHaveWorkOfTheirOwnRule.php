<?php

declare(strict_types=1);

namespace App\Tools\PhpStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

use function array_key_first;
use function count;
use function implode;
use function sprintf;
use function str_starts_with;
use function strrpos;
use function substr;

/**
 * A class that stands behind a facade interface has work of its own: it composes several classes of its vertical,
 * converts what they give, or adapts a call. A class under a `Facade` namespace that implements an interface of that
 * namespace, and whose every public method does nothing but hand its parameters on, as they are and in their order, to
 * one and the same property, is reported: it mirrors that class and is no contract, so the class that answers
 * implements the facade interface itself.
 *
 * What it cannot see is a class that hides its mirroring behind a statement that does nothing, and work that is none,
 * as a DTO copied into a facade DTO of the same fields, which NoDtoBesideAFacadeDtoOfTheSameFieldsRule reports.
 *
 * @implements Rule<InClassNode>
 */
final readonly class FacadeClassesHaveWorkOfTheirOwnRule implements Rule
{
    private const string FACADE = '\\Facade\\';

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $class = $node->getClassReflection();
        if (!$class->isClass() || $class->isAbstract() || !self::standsBehindAnInterfaceOfItsFacade($class)) {
            return [];
        }

        $answerers = [];
        foreach ($node->getOriginalNode()->getMethods() as $method) {
            if (!$method->isPublic() || str_starts_with($method->name->toString(), '__')) {
                continue;
            }
            $answerer = self::propertyHandedOnTo($method);
            if ($answerer === null) {
                return [];
            }
            $answerers[$answerer] = true;
        }
        if (count($answerers) !== 1) {
            return [];
        }

        $property = (string) array_key_first($answerers);
        $types    = $class->hasNativeProperty($property) ? $class->getNativeProperty($property)
            ->getReadableType()
            ->getObjectClassNames() : [];

        return [
            RuleErrorBuilder::message(sprintf(
                '%s only hands every call on to %s, so it is no contract of its own: the class that answers implements the facade interface itself.',
                $class->getName(),
                $types === [] ? '$' . $property : implode('|', $types)
            ))
                ->line($node->getOriginalNode()->getStartLine())
                ->identifier('meridian.facadeClassesHaveWorkOfTheirOwn')
                ->build(),
        ];
    }

    /**
     * Whether the class lies under a `Facade` namespace and implements an interface of that namespace.
     */
    private static function standsBehindAnInterfaceOfItsFacade(ClassReflection $class): bool
    {
        $at = strrpos($class->getName(), self::FACADE);
        if ($at === false) {
            return false;
        }
        $facade = substr($class->getName(), 0, $at) . self::FACADE;
        foreach ($class->getInterfaces() as $interface) {
            if (str_starts_with($interface->getName(), $facade)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The property of `$this` the method hands its parameters on to, as they are and in their order, doing nothing
     * else; null for a method that does anything else.
     */
    private static function propertyHandedOnTo(ClassMethod $method): ?string
    {
        if ($method->stmts === null || count($method->stmts) !== 1) {
            return null;
        }
        $statement = $method->stmts[0];
        $call      = $statement instanceof Return_ || $statement instanceof Expression ? $statement->expr : null;
        if (!$call instanceof MethodCall
            || $call->isFirstClassCallable()
            || !$call->name instanceof Identifier
            || !$call->var instanceof PropertyFetch
            || !$call->var->var instanceof Variable
            || $call->var->var->name !== 'this'
            || !$call->var->name instanceof Identifier
            || count($call->args) !== count($method->params)
        ) {
            return null;
        }
        foreach ($method->params as $position => $parameter) {
            $argument = $call->args[$position] ?? null;
            if ($parameter->variadic
                || !$parameter->var instanceof Variable
                || !$argument instanceof Arg
                || $argument->unpack
                || $argument->name !== null
                || !$argument->value instanceof Variable
                || $argument->value->name !== $parameter->var->name
            ) {
                return null;
            }
        }

        return $call->var->name->toString();
    }
}
