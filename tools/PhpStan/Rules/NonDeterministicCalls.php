<?php

declare(strict_types=1);

namespace App\Tools\PhpStan\Rules;

use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Type\ObjectType;

/**
 * Recognises the calls whose result differs from one run to the next, for the rules of code that must give the same
 * result whenever it runs: reads of the current time, and draws of randomness. Each method describes the call it
 * recognises, e.g. "calls time()", or returns null.
 */
final readonly class NonDeterministicCalls
{
    private const array CLOCKS          = ['App\Common\Domain\Service\DateAndTimeService', 'Symfony\Component\Clock\Clock'];
    private const array CLOCK_FUNCTIONS = [
        'date', 'date_create', 'date_create_immutable', 'getdate', 'gettimeofday', 'gmdate', 'gmmktime', 'hrtime', 'idate',
        'localtime', 'microtime', 'mktime', 'strtotime', 'time',
    ];
    private const array RANDOM_FUNCTIONS = [
        'array_rand', 'lcg_value', 'mt_rand', 'mt_srand', 'rand', 'random_bytes', 'random_int', 'shuffle', 'srand',
        'str_shuffle', 'uniqid',
    ];

    // The identifiers Symfony's Uid component generates: by a named constructor, or by a constructor without arguments.
    private const string UID           = 'Symfony\Component\Uid\AbstractUid';
    private const array UID_GENERATORS = ['generate', 'v1', 'v4', 'v6', 'v7'];
    private const array UID_CLASSES    = [
        'Symfony\Component\Uid\Ulid', 'Symfony\Component\Uid\UuidV1', 'Symfony\Component\Uid\UuidV4',
        'Symfony\Component\Uid\UuidV6', 'Symfony\Component\Uid\UuidV7',
    ];

    /**
     * A call of DateAndTimeService or of Symfony's Clock, now() on a PSR clock, or one of PHP's functions that read the
     * clock.
     */
    public function readOfTheClock(CallLike $node, Scope $scope): ?string
    {
        if (($node instanceof MethodCall || $node instanceof NullsafeMethodCall) && $node->name instanceof Identifier) {
            $type = $scope->getType($node->var);

            if ($node->name->toLowerString() === 'now' && new ObjectType('Psr\Clock\ClockInterface')
                ->isSuperTypeOf($type)
                ->yes()
            ) {
                return 'reads the current time from a clock';
            }
            foreach (self::CLOCKS as $clock) {
                if (new ObjectType($clock)->isSuperTypeOf($type)->yes()) {
                    return "calls {$clock}";
                }
            }
        }

        if ($node instanceof StaticCall && $node->class instanceof Name && in_array(
            $node->class->toString(),
            self::CLOCKS,
            true
        )) {
            return "calls {$node->class->toString()}";
        }

        if ($node instanceof FuncCall && $node->name instanceof Name && in_array(
            $node->name->toLowerString(),
            self::CLOCK_FUNCTIONS,
            true
        )) {
            return "calls {$node->name->toLowerString()}()";
        }

        return null;
    }

    /**
     * A call of one of PHP's functions that draw randomness, a new Random\Randomizer, an identifier Symfony's Uid
     * component generates, e.g. Uuid::v7() or new Ulid().
     */
    public function drawOfRandomness(CallLike $node, Scope $scope): ?string
    {
        if ($node instanceof FuncCall && $node->name instanceof Name && in_array(
            $node->name->toLowerString(),
            self::RANDOM_FUNCTIONS,
            true
        )) {
            return "calls {$node->name->toLowerString()}()";
        }

        if ($node instanceof StaticCall
            && $node->class instanceof Name
            && $node->name instanceof Identifier
            && in_array($node->name->toLowerString(), self::UID_GENERATORS, true)
            && new ObjectType(self::UID)->isSuperTypeOf(new ObjectType($scope->resolveName($node->class)))->yes()
        ) {
            return "generates an identifier with {$scope->resolveName($node->class)}::{$node->name->toString()}()";
        }

        if ($node instanceof New_ && $node->class instanceof Name) {
            $className = $scope->resolveName($node->class);
            if ($className === 'Random\Randomizer') {
                return 'creates a Random\Randomizer';
            }
            if (in_array($className, self::UID_CLASSES, true) && $node->getArgs() === []) {
                return "generates an identifier with new {$className}()";
            }
        }

        return null;
    }
}
