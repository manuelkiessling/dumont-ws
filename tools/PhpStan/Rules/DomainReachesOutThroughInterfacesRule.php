<?php

declare(strict_types=1);

namespace App\Tools\PhpStan\Rules;

use DateTimeInterface;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\UnionType;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

use function in_array;
use function is_string;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function strtolower;

/**
 * A class under `Domain` (in a `Domain` namespace, or a service named `…DomainService`) reaches outside `Domain`
 * through interfaces only: what its constructor takes from outside `Domain`, from a facade, from Doctrine, from Symfony
 * or from any other library, is an interface, so that the domain binds to no adapter and a test can stand one in. It
 * holds for every class under `Domain`, a domain service, a console command or an assignment alike, wherever the class
 * lies there.
 *
 * What lies under `Domain`, a sibling domain service, a policy, a reader of the vertical's own facts, is taken as the
 * class it is: an interface with one implementation and no test double says nothing, and a domain service has none of
 * its own. A value is taken as the class it is too, as an entity, an event or a DTO takes it: an enum, a date and time
 * (`DateTimeInterface`), or a class in a `Dto`, `Enum` or `ValueObject` namespace, a facade's DTOs among them.
 *
 * @implements Rule<ClassMethod>
 */
final readonly class DomainReachesOutThroughInterfacesRule implements Rule
{
    private const string DOMAIN          = '\\Domain\\';
    private const string VALUE_NAMESPACE = '/\\\\(Dto|Enum|ValueObject)\\\\/';

    public function __construct(private ReflectionProvider $reflectionProvider)
    {
    }

    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if ($node->name->toString() !== '__construct') {
            return [];
        }

        $class = $scope->getClassReflection();
        if ($class === null || !self::isUnderDomain($class->getName())) {
            return [];
        }

        $errors = [];
        foreach ($node->params as $param) {
            if ($param->type === null) {
                continue;
            }
            foreach (self::namedTypes($param->type) as $named) {
                $name = $scope->resolveName($named);
                if (!$this->reflectionProvider->hasClass($name)) {
                    continue;
                }
                $type = $this->reflectionProvider->getClass($name);
                if ($type->isInterface() || str_contains($type->getName(), self::DOMAIN) || self::isAValue($type)) {
                    continue;
                }
                $parameter = $param->var instanceof Variable && is_string($param->var->name) ? $param->var->name : 'param';
                $errors[]  = RuleErrorBuilder::message(sprintf(
                    'A class under Domain reaches outside Domain through interfaces only: parameter $%s of %s is typed to the concrete class %s, which is no value.',
                    $parameter,
                    $class->getName(),
                    $type->getName()
                ))->line($param->getLine())->identifier('meridian.domainReachesOutThroughInterfaces')->build();
            }
        }

        return $errors;
    }

    private static function isUnderDomain(string $class): bool
    {
        $short = ($pos = strrpos($class, '\\')) === false ? $class : substr($class, $pos + 1);

        return str_contains($class, self::DOMAIN) || str_ends_with($short, 'DomainService');
    }

    private static function isAValue(ClassReflection $type): bool
    {
        return $type->isEnum() || $type->implementsInterface(DateTimeInterface::class) || 1 === preg_match(
            self::VALUE_NAMESPACE,
            $type->getName()
        );
    }

    /**
     * @return list<Name>
     */
    private static function namedTypes(Node $type): array
    {
        if ($type instanceof Name) {
            return in_array(strtolower($type->toString()), ['self', 'static', 'parent'], true) ? [] : [$type];
        }
        if ($type instanceof NullableType) {
            return self::namedTypes($type->type);
        }
        if ($type instanceof UnionType || $type instanceof IntersectionType) {
            $names = [];
            foreach ($type->types as $inner) {
                $names = [...$names, ...self::namedTypes($inner)];
            }

            return $names;
        }

        return [];
    }
}
