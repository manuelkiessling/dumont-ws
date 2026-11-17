<?php

declare(strict_types=1);

namespace App\Tools\PhpStan\Rules;

use InvalidArgumentException;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Node\FileNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException;

/**
 * Holds the direction of the dependencies between verticals:
 * code under App\<Vertical>\ names classes of its own vertical, of Common, and of the verticals it may depend on, and of
 * no other. Only the verticals the configuration lists are checked; one it does not list may depend on any vertical.
 *
 * Each entry of the configuration is "<Vertical> -> <Vertical>, <Vertical>", or "<Vertical> ->" for a vertical that
 * may depend on none but Common. The business dependencies must not form a cycle. Explicit presentation-only
 * dependencies permit a page to display facts from a vertical that consumes its owner; they never permit a Domain,
 * Infrastructure or Api class to reach back. FeatureBoundariesArchTest still requires facade-only integration.
 *
 * Which layers of another vertical code may name (its Facade only) is FeatureBoundariesArchTest's concern.
 *
 * @implements Rule<FileNode>
 */
final class VerticalDependencyDirectionRule implements Rule
{
    private const string ENTRY = '/\A\s*([A-Z][A-Za-z0-9]*)\s*->\s*([A-Z][A-Za-z0-9]*(?:\s*,\s*[A-Z][A-Za-z0-9]*)*)?\s*\z/';

    /**
     * @var array<string, list<string>>
     */
    private array $allowedDependencies = [];

    /** @var array<string, list<string>> */
    private array $presentationDependencies = [];

    /**
     * @param list<string> $allowedDependencies
     * @param list<string> $presentationDependencies additional facade consumers under Presentation only; not domain dependency edges
     */
    public function __construct(array $allowedDependencies, array $presentationDependencies = [])
    {
        foreach ($allowedDependencies as $entry) {
            if (1 !== preg_match(self::ENTRY, $entry, $matches)) {
                throw new InvalidArgumentException("Not an allowed dependency, \"<Vertical> -> <Vertical>, …\": \"{$entry}\".");
            }
            if (array_key_exists($matches[1], $this->allowedDependencies)) {
                throw new InvalidArgumentException("The allowed dependencies of {$matches[1]} are configured twice.");
            }

            $this->allowedDependencies[$matches[1]] = ($matches[2] ?? '') === ''
                ? []
                : array_map(static fn (string $vertical): string => mb_trim($vertical), explode(',', $matches[2]));
        }

        foreach ($presentationDependencies as $entry) {
            if (1 !== preg_match(self::ENTRY, $entry, $matches)) {
                throw new InvalidArgumentException("Not a presentation dependency: {$entry}.");
            }
            if (array_key_exists($matches[1], $this->presentationDependencies)) {
                throw new InvalidArgumentException("The presentation dependencies of {$matches[1]} are configured twice.");
            }
            $this->presentationDependencies[$matches[1]] = ($matches[2] ?? '') === '' ? [] : array_map(
                mb_trim(...),
                explode(',', $matches[2])
            );
        }

        foreach (array_keys($this->allowedDependencies) as $vertical) {
            $this->refuseCycleFrom($vertical, [$vertical]);
        }
    }

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
            // A namespace names its vertical in its second segment, a class in its second of at least three.
            $vertical = $this->verticalOf(($namespace->name?->toString() ?? '') . '\\');
            if ($vertical === null || !array_key_exists($vertical, $this->allowedDependencies)) {
                continue;
            }

            $reported = [];
            foreach ($finder->findInstanceOf($namespace->stmts, Name::class) as $name) {
                $className = $name->toString();
                $target    = $this->verticalOf($className);
                if ($target === null || $target === $vertical || $target === 'Common' || in_array(
                    $target,
                    $this->allowedDependencies[$vertical],
                    true
                )) {
                    continue;
                }

                if (str_starts_with(
                    ($namespace->name?->toString() ?? '') . '\\',
                    'App\\' . $vertical . '\\Presentation\\'
                ) && in_array($target, $this->presentationDependencies[$vertical] ?? [], true)) {
                    continue;
                }

                $key = $name->getStartLine() . ' ' . $className;
                if (array_key_exists($key, $reported)) {
                    continue;
                }
                $reported[$key] = true;

                $errors[] = RuleErrorBuilder::message(sprintf(
                    '%s must not depend on %s: it names %s. Besides Common, %s may depend on %s (the allowed dependencies are configured in phpstan.dist.neon).',
                    $vertical,
                    $target,
                    $className,
                    $vertical,
                    $this->allowedDependencies[$vertical] === [] ? 'no other vertical' : implode(
                        ', ',
                        $this->allowedDependencies[$vertical]
                    ) . ' only'
                ))->line($name->getStartLine())->identifier('verticalDependencyDirection')->build();
            }
        }

        return $errors;
    }

    private function verticalOf(string $className): ?string
    {
        $segments = explode('\\', $className);

        return count($segments) > 2 && $segments[0] === 'App' ? $segments[1] : null;
    }

    /**
     * @param list<string> $path the verticals that lead to $vertical, and $vertical itself
     */
    private function refuseCycleFrom(string $vertical, array $path): void
    {
        foreach ($this->allowedDependencies[$vertical] ?? [] as $dependency) {
            if (in_array($dependency, $path, true)) {
                throw new InvalidArgumentException('The allowed dependencies between verticals form a cycle: ' . implode(
                    ' -> ',
                    [...$path, $dependency]
                ) . '.');
            }

            $this->refuseCycleFrom($dependency, [...$path, $dependency]);
        }
    }
}
