<?php

declare(strict_types=1);

namespace App\Tools\PhpCsFixer\Fixers;

use PhpCsFixer\Fixer\FixerInterface;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\CT;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use SplFileInfo;

/**
 * Puts the parameters of a declaration and the arguments of a call one below the other, each on a line of its own, once
 * there are four of them or more, or two or more on a line longer than 120 characters: an IDE that shows each
 * parameter's name in front of its value, and a diff, read such a list down a column instead of along a line. The
 * closing parenthesis stands on a line of its own, at the indentation of the line the list opens on;
 * `align_multiline_parameters` then aligns a declaration's types and names, and `braces_position` keeps the brace of a
 * function behind its `): type`.
 *
 * It looks at the parentheses of a function, a method, a closure and an arrow function, of a closure's `use`, of a
 * call of a function, a method, a variable or an expression, of `new` and of an attribute, and counts their elements
 * at the top level; a trailing comma counts none. A list of fewer elements it leaves as it is, on one line or on many,
 * unless it stands whole on a line that is too long: then it puts the longest such list of the line one below the
 * other, the outermost one first, and measures the lines that gives anew. Before that, it puts each link of a chain
 * of calls on such a line on a line of its own, `->from(…)` below `->select(…)`.
 * A line that is long by a string or by a list of one element stays long.
 * What a moved element spans over several lines, such as a closure or an array, moves with it: its lines are indented
 * by as much as its first line. It leaves alone a list with a comment between its elements, whose place it cannot
 * tell for sure.
 */
final class ArgumentsOneBelowTheOtherFixer implements FixerInterface
{
    private const int MINIMUM                = 4;
    private const int MINIMUM_ON_A_LONG_LINE = 2;
    private const int LONGEST_LINE           = 120;
    private const string INDENTATION         = '    ';

    public function getName(): string
    {
        return 'App/arguments_one_below_the_other';
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'From four on, or from two on a line that is too long, the parameters of a declaration and the arguments of a call stand one below the other.',
            [new CodeSample("<?php\nfunction close(string \$system, int \$representation, ?bool \$ended, string \$day): bool\n{\n    return render(\$system, \$representation, \$ended, \$day);\n}\n")]
        );
    }

    /**
     * Before the fixers that indent and align what it puts on lines of their own: the spaces after the commas
     * (`method_argument_space`), arrays, docblocks, statements, heredocs, parameters and operators.
     */
    public function getPriority(): int
    {
        return 35;
    }

    /**
     * It changes nothing but the whitespace between tokens outside of strings.
     */
    public function isRisky(): bool
    {
        return false;
    }

    public function supports(SplFileInfo $file): bool
    {
        return true;
    }

    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isTokenKindFound(',');
    }

    public function fix(SplFileInfo $file, Tokens $tokens): void
    {
        for ($index = 0; $index < $tokens->count(); ++$index) {
            if ($index === 0 || str_contains($tokens[$index - 1]->getContent(), "\n")) {
                $this->breakChainOnALongLine($tokens, $index);
            }
        }

        // From the front, so that a list inside another one is indented from the line its outer list puts it on
        for ($index = 0; $index < $tokens->count(); ++$index) {
            if (!$tokens[$index]->equals('(') || !$this->opensAList($tokens, $index)) {
                continue;
            }
            if ($this->fixList($tokens, $index) && $this->isTooLong($tokens, $index)) {
                // A list before it on the same line may now be the longest one that keeps the line too long
                $index = $this->lineStartAt($tokens, $index) - 1;
            }
        }
    }

    /**
     * Puts each link of a chain of calls on a line of its own, `->from(…)` below `->select(…)`, where the line that
     * starts at $lineStart is too long and holds two links or more, that is, two `->` or `?->` behind a call's closing
     * parenthesis within the same parentheses. A block its last link opens, such as a closure's body, moves with it.
     */
    private function breakChainOnALongLine(Tokens $tokens, int $lineStart): void
    {
        if (!$this->isTooLong($tokens, $lineStart)) {
            return;
        }

        // The links by the opening token of the block they stand in, -1 for none, and the depth of that block
        $links  = [];
        $depths = [-1 => 0];
        $blocks = [];
        $index  = $lineStart;
        for (; $index < $tokens->count() && !str_contains($tokens[$index]->getContent(), "\n"); ++$index) {
            $block = Tokens::detectBlockType($tokens[$index]);
            if ($block !== null && $block['isStart']) {
                $depths[$index] = count($blocks) + 1;
                $blocks[]       = $index;
                continue;
            }
            if ($block !== null) {
                array_pop($blocks);
                continue;
            }
            if ($tokens[$index]->isObjectOperator()) {
                $previous = $tokens->getPrevMeaningfulToken($index);
                if ($previous !== null && $tokens[$previous]->equals(')')) {
                    $links[array_last($blocks) ?? -1][] = $index;
                }
            }
        }
        $chain = null;
        foreach ($links as $opening => $operators) {
            if (count($operators) >= 2 && ($chain === null || $depths[$opening] < $depths[$chain])) {
                $chain = $opening;
            }
        }
        if ($chain === null || in_array($chain, $blocks, true)) {
            // A chain inside a block that goes on below the line is left as it is
            return;
        }

        $first = array_first($blocks);
        if ($first !== null) {
            // The last link opens a block that goes on below the line, such as a closure's body: it moves with the link
            $this->shiftLines(
                $tokens,
                $index,
                $tokens->findBlockEnd(
                    Tokens::detectBlockType($tokens[$first])['type'] ?? Tokens::BLOCK_TYPE_PARENTHESIS,
                    $first
                ),
                mb_strlen(self::INDENTATION)
            );
        }

        $inner = $this->indentationOfLineAt($tokens, $lineStart) . self::INDENTATION;
        foreach (array_reverse($links[$chain]) as $operator) {
            $this->breakBefore($tokens, $operator, $inner);
        }
    }

    /**
     * The index of the first token of the line the token at $index stands on.
     */
    private function lineStartAt(Tokens $tokens, int $index): int
    {
        for ($cursor = $index - 1; $cursor >= 0; --$cursor) {
            if (str_contains($tokens[$cursor]->getContent(), "\n")) {
                return $cursor + 1;
            }
        }

        return 0;
    }

    private function opensAList(Tokens $tokens, int $openingParenthesis): bool
    {
        $previous = $tokens->getPrevMeaningfulToken($openingParenthesis);

        return $previous !== null && $tokens[$previous]->equalsAny([
            [T_STRING],
            [T_VARIABLE],
            [T_FUNCTION],
            [T_FN],
            [T_STATIC],
            [T_CLASS],
            [CT::T_USE_LAMBDA],
            ')',
            ']',
        ]);
    }

    /**
     * Whether it changed the list that opens at $openingParenthesis, putting it one below the other.
     */
    private function fixList(Tokens $tokens, int $openingParenthesis): bool
    {
        $closingParenthesis = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS, $openingParenthesis);
        $elements           = $this->elementsBetween($tokens, $openingParenthesis, $closingParenthesis);
        if ($elements === null || !$this->wantsToStandOneBelowTheOther(
            $tokens,
            $openingParenthesis,
            $closingParenthesis,
            count($elements)
        )) {
            return false;
        }

        $before      = $tokens->generatePartialCode($openingParenthesis, $closingParenthesis);
        $indentation = $this->indentationOfLineAt($tokens, $openingParenthesis);
        $inner       = $indentation . self::INDENTATION;

        // From the back, as each change of whitespace may add a token and so move the indexes after it
        $this->breakBefore($tokens, $closingParenthesis, $indentation);
        foreach (array_reverse($elements) as [$start, $end]) {
            $shift = mb_strlen($inner) - mb_strlen($this->indentationOfLineAt($tokens, $start));
            $this->shiftLines(
                $tokens,
                $start,
                $end,
                $shift
            );
            $this->breakBefore($tokens, $start, $inner);
        }

        return $tokens->generatePartialCode(
            $openingParenthesis,
            $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS, $openingParenthesis)
        ) !== $before;
    }

    private function wantsToStandOneBelowTheOther(
        Tokens $tokens,
        int    $openingParenthesis,
        int    $closingParenthesis,
        int    $count
    ): bool {
        if ($count >= self::MINIMUM) {
            return true;
        }

        return $count >= self::MINIMUM_ON_A_LONG_LINE
            && $this->standsOnOneLine($tokens, $openingParenthesis, $closingParenthesis)
            && $this->isTooLong($tokens, $openingParenthesis)
            && !$this->aLongerListFollowsOnTheLine(
                $tokens,
                $closingParenthesis,
                $this->lengthOf($tokens, $openingParenthesis, $closingParenthesis)
            );
    }

    /**
     * Whether a list of two elements or more that stands whole on the same line, after the one that closes at
     * $closingParenthesis and not inside it, is longer than its $length characters.
     */
    private function aLongerListFollowsOnTheLine(Tokens $tokens, int $closingParenthesis, int $length): bool
    {
        for ($index = $closingParenthesis + 1; $index < $tokens->count(); ++$index) {
            if (str_contains($tokens[$index]->getContent(), "\n")) {
                return false;
            }
            if (!$tokens[$index]->equals('(') || !$this->opensAList($tokens, $index)) {
                continue;
            }
            $close    = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS, $index);
            $elements = $this->elementsBetween($tokens, $index, $close);
            if ($this->lengthOf($tokens, $index, $close) > $length
                && $elements !== null
                && count($elements) >= self::MINIMUM_ON_A_LONG_LINE
                && $this->standsOnOneLine($tokens, $index, $close)
            ) {
                return true;
            }
        }

        return false;
    }

    private function lengthOf(Tokens $tokens, int $first, int $last): int
    {
        return mb_strlen($tokens->generatePartialCode($first, $last));
    }

    private function standsOnOneLine(Tokens $tokens, int $first, int $last): bool
    {
        return !str_contains($tokens->generatePartialCode($first, $last), "\n");
    }

    /**
     * Whether the line the token at $index stands on is longer than 120 characters, counting one space where several
     * stand after its indentation: the spaces by which `binary_operator_spaces` aligns `=` and `=>` change with what
     * stands on the lines around it, and would make what this fixer does change from one run to the next.
     */
    private function isTooLong(Tokens $tokens, int $index): bool
    {
        return mb_strlen((string) preg_replace(
            '/(?<=\S)[ \t]{2,}/',
            ' ',
            $this->lineAt($tokens, $index)
        )) > self::LONGEST_LINE;
    }

    /**
     * The whole line the token at $index stands on, without its line break.
     */
    private function lineAt(Tokens $tokens, int $index): string
    {
        $before = '';
        for ($cursor = $index - 1; $cursor >= 0; --$cursor) {
            $content = $tokens[$cursor]->getContent();
            $break   = mb_strrpos($content, "\n");
            if ($break !== false) {
                $before = mb_substr($content, $break + 1) . $before;
                break;
            }
            $before = $content . $before;
        }

        $after = '';
        for ($cursor = $index; $cursor < $tokens->count(); ++$cursor) {
            $content = $tokens[$cursor]->getContent();
            $break   = mb_strpos($content, "\n");
            if ($break !== false) {
                $after .= mb_substr($content, 0, $break);
                break;
            }
            $after .= $content;
        }

        return $before . $after;
    }

    /**
     * The first and the last token of each element between the parentheses, the whitespace around it excluded; null
     * where a comment stands between them, at their top level.
     *
     * @return list<array{int, int}>|null
     */
    private function elementsBetween(Tokens $tokens, int $openingParenthesis, int $closingParenthesis): ?array
    {
        $elements = [];
        $start    = $openingParenthesis + 1;
        for ($index = $start; $index <= $closingParenthesis; ++$index) {
            $block = Tokens::detectBlockType($tokens[$index]);
            if ($block !== null && $block['isStart']) {
                $index = $tokens->findBlockEnd($block['type'], $index);
                continue;
            }
            if ($tokens[$index]->isComment()) {
                return null;
            }
            if ($index < $closingParenthesis && !$tokens[$index]->equals(',')) {
                continue;
            }

            $first = $start;
            $last  = $index - 1;
            while ($first <= $last && $tokens[$first]->isWhitespace()) {
                ++$first;
            }
            while ($last >= $first && $tokens[$last]->isWhitespace()) {
                --$last;
            }
            if ($first <= $last) {
                $elements[] = [$first, $last];
            }
            $start = $index + 1;
        }

        return $elements;
    }

    /**
     * Makes the whitespace in front of the token at $index a line break followed by $indentation.
     */
    private function breakBefore(Tokens $tokens, int $index, string $indentation): void
    {
        $tokens->ensureWhitespaceAtIndex($index - 1, 1, "\n" . $indentation);
    }

    /**
     * Indents each line that starts between $start and $end by $shift columns more, or by -$shift less, as far as it is
     * indented; a line inside a string stays as it is.
     */
    private function shiftLines(
        Tokens $tokens,
        int    $start,
        int    $end,
        int    $shift
    ): void {
        if ($shift === 0) {
            return;
        }

        for ($index = $start; $index <= $end; ++$index) {
            $token = $tokens[$index];
            if (!$token->isWhitespace() && !$token->isComment()) {
                continue;
            }
            $content = $token->getContent();
            if (!str_contains($content, "\n")) {
                continue;
            }

            $shifted = preg_replace_callback(
                '/\n([ \t]*+)(?!\n)/',
                static fn (array $match): string => "\n" . ($shift > 0
                    ? $match[1] . str_repeat(' ', $shift)
                    : mb_substr($match[1], min(-$shift, mb_strlen($match[1])))),
                $content
            );
            if ($shifted !== null && $shifted !== $content) {
                $tokens[$index] = new Token([$token->getId() ?? T_WHITESPACE, $shifted]);
            }
        }
    }

    /**
     * The whitespace at the start of the line the token at $index stands on.
     */
    private function indentationOfLineAt(Tokens $tokens, int $index): string
    {
        for ($cursor = $index - 1; $cursor >= 0; --$cursor) {
            $content = $tokens[$cursor]->getContent();
            $break   = mb_strrpos($content, "\n");
            if ($break === false) {
                continue;
            }
            $rest = mb_substr($content, $break + 1);
            if (!$tokens[$cursor]->isWhitespace()) {
                // The line starts inside this token, a comment or a string: its indentation, if it has one
                return mb_substr($rest, 0, mb_strlen($rest) - mb_strlen(ltrim($rest, " \t")));
            }

            return $rest;
        }

        return '';
    }
}
