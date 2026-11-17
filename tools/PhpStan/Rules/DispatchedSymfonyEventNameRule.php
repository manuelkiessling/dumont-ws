<?php

declare(strict_types=1);

namespace App\Tools\PhpStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\ObjectType;

/**
 * Symfony event classes end with "SymfonyEvent". This rule holds it for what
 * the application dispatches: a class of the application whose object is passed to dispatch() of an event dispatcher,
 * Symfony's or PSR-14's, ends with "SymfonyEvent". An event whose type is only known as object, as the facade events
 * the event-sourcing platform dispatches, is SymfonyEventClassNameRule's concern; Messenger's buses dispatch messages,
 * not events.
 *
 * @implements Rule<MethodCall>
 */
final class DispatchedSymfonyEventNameRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @return list<IdentifierRuleError>
     *
     * @throws ShouldNotHappenException
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Identifier
            || $node->name->toLowerString() !== 'dispatch'
            || !new ObjectType('Psr\EventDispatcher\EventDispatcherInterface')
                ->isSuperTypeOf($scope->getType($node->var))
                ->yes()
        ) {
            return [];
        }

        $event = $node->getArgs()[0] ?? null;
        if (!$event instanceof Arg) {
            return [];
        }

        $errors = [];
        foreach ($scope->getType($event->value)->getObjectClassNames() as $className) {
            if (!str_starts_with($className, 'App\\') || str_ends_with($className, 'SymfonyEvent')) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(sprintf(
                'The class %s is dispatched as a Symfony event, so its name ends with "SymfonyEvent".',
                $className
            ))->identifier('symfonyEventName.dispatched')->build();
        }

        return $errors;
    }
}
