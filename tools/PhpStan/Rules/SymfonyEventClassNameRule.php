<?php

declare(strict_types=1);

namespace App\Tools\PhpStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException;

/**
 * Symfony event classes end with "SymfonyEvent". This rule holds it for every
 * class of the application in a SymfonyEvent namespace, such as the facade events in <Vertical>\Facade\SymfonyEvent\,
 * and for every class of the application that extends Symfony's Event. DispatchedSymfonyEventNameRule holds it for
 * what the application dispatches.
 *
 * @implements Rule<InClassNode>
 */
final class SymfonyEventClassNameRule implements Rule
{
    private const string SYMFONY_EVENT = 'Symfony\Contracts\EventDispatcher\Event';

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
        $class     = $node->getClassReflection();
        $className = $class->getName();
        if (!str_starts_with($className, 'App\\') || str_ends_with($className, 'SymfonyEvent') || !$class->isClass()) {
            return [];
        }

        $reason = match (true) {
            str_contains($className, '\\SymfonyEvent\\') => 'it is in a SymfonyEvent namespace',
            $class->isSubclassOf(self::SYMFONY_EVENT)    => 'it extends ' . self::SYMFONY_EVENT,
            default                                      => null,
        };
        if ($reason === null) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'The class %s is a Symfony event, as %s, so its name ends with "SymfonyEvent".',
                $className,
                $reason
            ))->identifier('symfonyEventName.class')->build(),
        ];
    }
}
