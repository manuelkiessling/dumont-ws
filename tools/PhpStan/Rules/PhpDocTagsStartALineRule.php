<?php

declare(strict_types=1);

namespace App\Tools\PhpStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt;
use PHPStan\Analyser\Scope;
use PHPStan\Node\VirtualNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException;

/**
 * A PHPDoc tag starts a line of its comment. PHPDoc reads `@param`, `@return`, `@var` and `@throws` only there: written
 * after text, as `/** The items. @return list<Item> *\/`, or after another tag on the same line, it is part of that
 * text, and the type it gives is lost to PHPStan, which then knows the value only as `mixed` or as an array of nothing
 * known. Tags named in backticks, as here, are prose and not reported.
 *
 * @implements Rule<Node>
 */
final class PhpDocTagsStartALineRule implements Rule
{
    private const string TAG = '/(?<![{\w])@(param|return|var|throws|template|phpstan-[\w-]+|psalm-[\w-]+)\b/';

    public function getNodeType(): string
    {
        return Node::class;
    }

    /**
     * @return list<IdentifierRuleError>
     *
     * @throws ShouldNotHappenException
     */
    public function processNode(Node $node, Scope $scope): array
    {
        // PHPStan's own nodes repeat the comment of the node they stand for
        if ((!$node instanceof Stmt && !$node instanceof Param) || $node instanceof VirtualNode) {
            return [];
        }

        $comment = $node->getDocComment();
        if ($comment === null) {
            return [];
        }

        $errors = [];
        foreach (explode("\n", $comment->getText()) as $offset => $line) {
            // What a line says, without the comment's own marks, and without what it quotes in backticks
            $text = preg_replace(
                '/`[^`]*`/',
                '',
                (string) preg_replace('/\A\s*(\/\*\*|\*\/|\*)?\s*/', '', $line)
            )                                                 ?? '';
            $text = preg_replace('/\s*\*\/\s*\z/', '', $text) ?? '';

            if (1 !== preg_match_all(
                self::TAG,
                $text,
                $tags,
                PREG_OFFSET_CAPTURE
            ) && $tags[0] === []) {
                continue;
            }
            foreach ($tags[0] as [$tag, $position]) {
                if ($position === 0) {
                    continue;
                }
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'The PHPDoc tag %s does not start its line, so PHPDoc reads it as text and its type is lost: put every tag on a line of its own.',
                    $tag
                ))->line($comment->getStartLine() + $offset)->identifier('phpDocTagsStartALine')->build();
            }
        }

        return $errors;
    }
}
