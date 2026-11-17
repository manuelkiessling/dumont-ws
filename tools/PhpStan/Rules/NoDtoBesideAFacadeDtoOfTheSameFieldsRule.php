<?php

declare(strict_types=1);

namespace App\Tools\PhpStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use ReflectionProperty;

use function count;
use function is_string;
use function preg_match;
use function sort;
use function sprintf;
use function str_starts_with;
use function strlen;
use function strpos;
use function substr;

/**
 * A vertical keeps no DTO of its own beside a facade DTO of the same fields: it reads into the facade's DTO, and its
 * own code uses that. Reported is a `new` of a class under a vertical's `Facade\Dto` whose every argument is the
 * property, of the parameter's name, of one and the same object, where that object is a class of a `Dto` namespace of
 * the same vertical outside its facade and has no public property beyond those handed over: such a copy converts
 * nothing, wherever it stands.
 *
 * A facade DTO that leaves a field out, renames one or works on one tells other verticals something else than the
 * vertical keeps, and is not reported; neither is one made of an entity or a value object, which a facade converts.
 * What it cannot see is a copy made through a function, an array or the properties of an object whose class the
 * analysis does not know.
 *
 * @implements Rule<New_>
 */
final readonly class NoDtoBesideAFacadeDtoOfTheSameFieldsRule implements Rule
{
    private const string FACADE     = 'Facade\\';
    private const string FACADE_DTO = '\\Facade\\Dto\\';
    private const string DTO        = '/(\A|\\\\)Dto\\\\/';

    public function __construct(private ReflectionProvider $reflectionProvider)
    {
    }

    public function getNodeType(): string
    {
        return New_::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->class instanceof Name || $node->args === []) {
            return [];
        }
        $made = $scope->resolveName($node->class);
        $at   = strpos($made, self::FACADE_DTO);
        if ($at === false || !$this->reflectionProvider->hasClass($made)) {
            return [];
        }
        // The vertical's namespace, with the backslash that ends it
        $vertical = substr($made, 0, $at + 1);

        $handed = [];
        $object = null;
        foreach ($node->args as $position => $argument) {
            if (!$argument instanceof Arg
                || $argument->unpack
                || !$argument->value instanceof PropertyFetch
                || !$argument->value->name instanceof Identifier
                || !$argument->value->var instanceof Variable
                || !is_string($argument->value->var->name)
                || ($object !== null && $object->name !== $argument->value->var->name)
            ) {
                return [];
            }
            $object                                            = $argument->value->var;
            $handed[$argument->name?->toString() ?? $position] = $argument->value->name->toString();
        }
        $classes = $scope->getType($object)->getObjectClassNames();
        if (count($classes) !== 1 || !$this->reflectionProvider->hasClass($classes[0])) {
            return [];
        }
        $from   = $classes[0];
        $inside = str_starts_with($from, $vertical) ? substr($from, strlen($vertical)) : null;
        if ($inside === null || str_starts_with($inside, self::FACADE) || preg_match(self::DTO, $inside) !== 1) {
            return [];
        }

        // Every parameter of the facade's DTO takes the property of its own name, and no property is left over
        $parameters = $this->reflectionProvider->getClass($made)
            ->getNativeReflection()
            ->getConstructor()
            ?->getParameters() ?? [];
        if (count($parameters) !== count($handed)) {
            return [];
        }
        foreach ($parameters as $position => $parameter) {
            if (($handed[$parameter->getName()] ?? $handed[$position] ?? null) !== $parameter->getName()) {
                return [];
            }
        }
        $fields = [];
        foreach ($this->reflectionProvider->getClass($from)
            ->getNativeReflection()
            ->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if (!$property->isStatic()) {
                $fields[] = $property->getName();
            }
        }
        $copied = array_values($handed);
        sort($fields);
        sort($copied);
        if ($fields !== $copied) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                '%s is copied field by field from %s, which has the same fields: the vertical reads into the facade\'s DTO and keeps none of its own beside it.',
                $made,
                $from
            ))->identifier('meridian.noDtoBesideAFacadeDtoOfTheSameFields')->build(),
        ];
    }
}
