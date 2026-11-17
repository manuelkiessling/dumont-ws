<?php

declare(strict_types=1);

namespace App\Tools\PhpStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\UnionType;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException;

/**
 * What is one of a closed set is passed around as a case of an enum, never as a string. In a vertical's `Domain` and
 * `Facade`, the layers whose signatures the rest of the application programs against, nothing that is named a kind, a
 * type, a status or a state is a string: no parameter and no property named `$kind`, `$type`, `$status`, `$state` or
 * `$…Kind`, `$…Type`, `$…Status`, `$…State`, and no return of a method so named that takes nothing, which is how such a
 * value is read; a method that is given something and answers in words (`nameOfKind($kind)`) is none. What a class
 * constant held as a magic string (`'done'`) is then a case the compiler and PHPStan know (`ItemState::Done`).
 *
 * A string stays where the outside defines the value, and the rule leaves those layers alone: a column a read model
 * reads, a field of a form, a line of a data situation. There the string is made a case as it comes in (`tryFrom()`,
 * or a method like `MediaType::of()` that has a case for what it does not know) and a value as it leaves.
 *
 * It goes by the name, which is how the application names such a thing; a closed set under another name is not seen.
 *
 * @implements Rule<ClassLike>
 */
final class ClosedSetsAreEnumsRule implements Rule
{
    private const string LAYERS = '/^App\\\\[A-Za-z0-9]+\\\\(Domain|Facade)\\\\/';
    private const string WHY    = ' A kind, a type, a status and a state are one of a closed set: give the set an enum and pass its cases.';

    /** What the application calls a value of a closed set, as a name's ending or the whole name. */
    private const array NAMES = ['Kind', 'Type', 'Status', 'State'];

    public function getNodeType(): string
    {
        return ClassLike::class;
    }

    /**
     * @return list<IdentifierRuleError>
     *
     * @throws ShouldNotHappenException
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $class = $node->namespacedName?->toString();
        if ($class === null || preg_match(self::LAYERS, $class) !== 1) {
            return [];
        }

        $errors = [];
        foreach ($node->getProperties() as $property) {
            foreach ($property->props as $declared) {
                if (self::namesAClosedSet($declared->name->toString()) && self::isAString($property->type)) {
                    $errors[] = self::error(
                        sprintf('The property $%s of %s is a string.', $declared->name->toString(), $class),
                        $property->getStartLine()
                    );
                }
            }
        }
        foreach ($node->getMethods() as $method) {
            $name = $method->name->toString();
            foreach ($method->params as $parameter) {
                if ($parameter->var instanceof Node\Expr\Variable && is_string($parameter->var->name) && self::namesAClosedSet($parameter->var->name) && self::isAString($parameter->type)) {
                    $errors[] = self::error(
                        sprintf(
                            'The parameter $%s of %s::%s() is a string.',
                            $parameter->var->name,
                            $class,
                            $name
                        ),
                        $parameter->getStartLine()
                    );
                }
            }
            if (self::namesAClosedSet($name) && $method->params === [] && self::isAString($method->returnType)) {
                $errors[] = self::error(sprintf('%s::%s() returns a string.', $class, $name), $method->getStartLine());
            }
        }

        return $errors;
    }

    /**
     * `kind`, `type`, `status` or `state`, or a name that ends in one of them: `subjectKind`, `getSubjectKind`.
     */
    private static function namesAClosedSet(string $name): bool
    {
        return array_any(
            self::NAMES,
            static fn (string $ending): bool => $name === lcfirst($ending) || str_ends_with($name, $ending)
        );
    }

    /**
     * Whether a declared type is `string`, alone, nullable or among others.
     */
    private static function isAString(?Node $type): bool
    {
        return match (true) {
            $type instanceof Identifier                                   => $type->toLowerString() === 'string',
            $type instanceof NullableType                                 => self::isAString($type->type),
            $type instanceof UnionType, $type instanceof IntersectionType => array_any(
                $type->types,
                self::isAString(...)
            ),
            default => false,
        };
    }

    /**
     * @throws ShouldNotHappenException
     */
    private static function error(string $what, int $line): IdentifierRuleError
    {
        return RuleErrorBuilder::message($what . self::WHY)->line($line)->identifier('closedSetsAreEnums')->build();
    }
}
