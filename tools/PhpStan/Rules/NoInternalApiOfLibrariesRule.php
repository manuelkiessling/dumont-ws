<?php

declare(strict_types=1);

namespace App\Tools\PhpStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException;

/**
 * The application uses only the public API of its libraries: no method and no class that a library marks `@internal`,
 * as `Symfony\Component\Tui\Tui::scheduleInterval()`. What a library keeps to itself may change in any release, without
 * a deprecation first. The application's own `@internal` elements are its own business.
 *
 * @implements Rule<CallLike>
 */
final readonly class NoInternalApiOfLibrariesRule implements Rule
{
    public function __construct(
        private ReflectionProvider $reflectionProvider
    ) {
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
        $used = match (true) {
            $node instanceof MethodCall, $node instanceof NullsafeMethodCall => $this->calledMethods($node, $scope),
            $node instanceof StaticCall                                      => $this->calledStaticMethods($node, $scope),
            $node instanceof New_                                            => $this->createdClasses($node, $scope),
            default                                                          => [],
        };

        return array_map(
            static fn (string $element): IdentifierRuleError => RuleErrorBuilder::message(sprintf(
                '%s is marked @internal by its library: the application uses only a library\'s public API.',
                $element
            ))->identifier('noInternalApiOfLibraries')->build(),
            $used
        );
    }

    /**
     * @return list<string>
     */
    private function calledMethods(MethodCall|NullsafeMethodCall $node, Scope $scope): array
    {
        if (!$node->name instanceof Identifier) {
            return [];
        }

        $used = [];
        foreach ($scope->getType($node->var)->getObjectClassNames() as $className) {
            $used = [...$used, ...$this->internalMethod($className, $node->name->toString(), $scope)];
        }

        return $used;
    }

    /**
     * @return list<string>
     */
    private function calledStaticMethods(StaticCall $node, Scope $scope): array
    {
        if (!$node->class instanceof Name || !$node->name instanceof Identifier) {
            return [];
        }

        return $this->internalMethod($scope->resolveName($node->class), $node->name->toString(), $scope);
    }

    /**
     * @return list<string>
     */
    private function createdClasses(New_ $node, Scope $scope): array
    {
        if (!$node->class instanceof Name) {
            return [];
        }

        $className = $scope->resolveName($node->class);
        if ($this->isOwnCode($className) || !$this->reflectionProvider->hasClass($className)) {
            return [];
        }

        return $this->reflectionProvider->getClass($className)->isInternal() ? ["The class {$className}"] : [];
    }

    /**
     * @return list<string>
     */
    private function internalMethod(string $className, string $methodName, Scope $scope): array
    {
        if (!$this->reflectionProvider->hasClass($className)) {
            return [];
        }

        $class = $this->reflectionProvider->getClass($className);
        if (!$class->hasMethod($methodName)) {
            return [];
        }

        $method    = $class->getMethod($methodName, $scope);
        $declaring = $method->getDeclaringClass();
        if ($this->isOwnCode($declaring->getName())) {
            return [];
        }

        return $method->isInternal()->yes() || $declaring->isInternal()
            ? ["{$declaring->getName()}::{$method->getName()}()"]
            : [];
    }

    private function isOwnCode(string $className): bool
    {
        return str_starts_with($className, 'App\\');
    }
}
