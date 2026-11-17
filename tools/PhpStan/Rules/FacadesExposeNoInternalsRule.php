<?php

declare(strict_types=1);

namespace App\Tools\PhpStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\Type;

/**
 * Facades pass and return DTOs, value objects, enums and primitives, never models. What a class, interface or enum
 * under App\<Vertical>\Facade\ exposes, the types of the parameters and return values of its public methods
 * (constructors aside) and of its public properties, PHPDoc included, names no class of a vertical's Domain,
 * Infrastructure, Presentation, Api or TestHarness layer, its own vertical's included, and no model of business state
 * (a class under Domain\Model). Common's classes are open to every vertical, its models aside.
 *
 * @implements Rule<InClassNode>
 */
final readonly class FacadesExposeNoInternalsRule implements Rule
{
    private const string FACADE   = '/\AApp\\\\[^\\\\]+\\\\Facade\\\\/';
    private const string INTERNAL = '/\AApp\\\\(?!Common\\\\)[^\\\\]+\\\\(Domain|Infrastructure|Presentation|Api|TestHarness)\\\\/';
    private const string MODEL    = '/\AApp\\\\[^\\\\]+\\\\Domain\\\\Model\\\\/';

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * @return list<IdentifierRuleError>
     *
     * @throws ShouldNotHappenException
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $class = $node->getClassReflection();
        if (1 !== preg_match(self::FACADE, $class->getName())) {
            return [];
        }

        $errors = [];
        foreach ($node->getOriginalNode()->getMethods() as $method) {
            if (!$method->isPublic() || $method->name->toLowerString() === '__construct') {
                continue;
            }

            foreach ($this->exposedTypesOfMethod($class, $method) as $what => $type) {
                $errors = [...$errors, ...$this->errorsFor(
                    $class,
                    $what,
                    $type,
                    $method->getStartLine()
                )];
            }
        }

        foreach ($node->getOriginalNode()->getProperties() as $property) {
            if (!$property->isPublic()) {
                continue;
            }
            foreach ($property->props as $item) {
                $name   = $item->name->toString();
                $errors = [...$errors, ...$this->errorsFor(
                    $class,
                    "property \${$name}",
                    $class->getNativeProperty($name)->getReadableType(),
                    $property->getStartLine()
                )];
            }
        }

        $constructor = $node->getOriginalNode()->getMethod('__construct');
        foreach ($constructor === null ? [] : $constructor->params as $parameter) {
            if (!$parameter->isPromoted() || !$parameter->isPublic() || !$parameter->var instanceof Node\Expr\Variable || !is_string($parameter->var->name)) {
                continue;
            }
            $name   = $parameter->var->name;
            $errors = [...$errors, ...$this->errorsFor(
                $class,
                "property \${$name}",
                $class->getNativeProperty($name)->getReadableType(),
                $parameter->getStartLine()
            )];
        }

        return $errors;
    }

    /**
     * @return iterable<string, Type>
     */
    private function exposedTypesOfMethod(ClassReflection $class, ClassMethod $method): iterable
    {
        $name = $method->name->toString();
        foreach ($class->getNativeMethod($name)->getVariants() as $variant) {
            foreach ($variant->getParameters() as $parameter) {
                yield "parameter \${$parameter->getName()} of {$name}()" => $parameter->getType();
            }
            yield "return type of {$name}()" => $variant->getReturnType();
        }
    }

    /**
     * @return list<IdentifierRuleError>
     *
     * @throws ShouldNotHappenException
     */
    private function errorsFor(
        ClassReflection $class,
        string          $what,
        Type            $type,
        int             $line
    ): array {
        $errors = [];
        foreach ($type->getReferencedClasses() as $referenced) {
            $reason = $this->whyNotExposable($referenced);
            if ($reason === null) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(sprintf(
                'The %s of facade %s is %s, %s. A facade passes and returns DTOs, value objects, enums and primitives.',
                $what,
                $class->getName(),
                $referenced,
                $reason
            ))->line($line)->identifier('facadesExposeNoInternals')->build();
        }

        return $errors;
    }

    private function whyNotExposable(string $className): ?string
    {
        if (1 === preg_match(self::INTERNAL, $className, $matches)) {
            return "a class of the {$matches[1]} layer";
        }

        if (1 === preg_match(self::MODEL, $className)) {
            return 'a model of business state';
        }

        return null;
    }
}
