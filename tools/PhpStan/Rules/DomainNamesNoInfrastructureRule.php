<?php

declare(strict_types=1);

namespace App\Tools\PhpStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Node\FileNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException;

/**
 * Code under App\<Vertical>\Domain names nothing under an App\<Vertical>\Infrastructure: what the domain needs from
 * outside it reaches through a port, an interface under Domain that an adapter under Infrastructure implements, and the
 * types it speaks in lie under Domain. So the domain binds to no adapter wherever its classes lie, and a class that
 * moves out of Domain while Domain still names it is reported.
 *
 * An import is not reported; the names it brings in are, where code uses them.
 *
 * What it cannot see is a type named only in a PHPDoc, and a class named by a string.
 *
 * @implements Rule<FileNode>
 */
final readonly class DomainNamesNoInfrastructureRule implements Rule
{
    private const string DOMAIN         = '/\AApp\\\\(?!Tests\\\\|Tools\\\\)[^\\\\]+\\\\Domain(\\\\|\z)/';
    private const string INFRASTRUCTURE = '/\AApp\\\\[^\\\\]+\\\\Infrastructure\\\\/';

    public function getNodeType(): string
    {
        return FileNode::class;
    }

    /**
     * @return list<IdentifierRuleError>
     *
     * @throws ShouldNotHappenException
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];
        $finder = new NodeFinder();

        foreach ($finder->findInstanceOf($node->getNodes(), Namespace_::class) as $namespace) {
            if (1 !== preg_match(self::DOMAIN, $namespace->name?->toString() ?? '')) {
                continue;
            }

            $notReported = [];
            foreach ($finder->find(
                $namespace->stmts,
                static fn (Node $node): bool => $node instanceof Use_ || $node instanceof GroupUse
            ) as $exempt) {
                foreach ($finder->findInstanceOf([$exempt], Name::class) as $name) {
                    $notReported[spl_object_id($name)] = true;
                }
            }

            $reported = [];
            foreach ($finder->findInstanceOf($namespace->stmts, Name::class) as $name) {
                $className = $name->toString();
                if (array_key_exists(
                    spl_object_id($name),
                    $notReported
                ) || 1 !== preg_match(self::INFRASTRUCTURE, $className)) {
                    continue;
                }

                $key = $name->getStartLine() . ' ' . $className;
                if (array_key_exists($key, $reported)) {
                    continue;
                }
                $reported[$key] = true;

                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Domain code names %s, which lies under Infrastructure. The domain reaches outside it through a port under Domain that an adapter implements, and speaks in types under Domain.',
                    $className
                ))->line($name->getStartLine())->identifier('domainNamesNoInfrastructure')->build();
            }
        }

        return $errors;
    }
}
