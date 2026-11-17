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
use ReflectionClass;
use SplFileInfo;

/**
 * Puts the named arguments of an attribute in the order in which its constructor declares the parameters, as
 * PhpStorm's inspection "Named arguments order does not match parameters order" asks: `#[Route(path: …, name: …,
 * defaults: …, methods: …)]`, as `Route::__construct()` declares them. Positional arguments stay in front.
 *
 * It moves each argument whole, with a comment in front of it, and leaves the whitespace and the commas between the
 * arguments where they are: a multi-line attribute keeps its lines and its aligned colons. Rector's
 * `SortAttributeNamedArgsRector` orders the same, but prints the attribute anew on one line.
 *
 * It finds the attribute's class by the file's namespace and imports, and its constructor by reflection, so the class
 * must be autoloadable where PHP CS Fixer runs, as every class of the application and its dependencies is. It leaves
 * an attribute alone whose class it does not find, that has no constructor or a variadic parameter, or that is given
 * a name its constructor does not declare. It does not look at the named arguments of calls, which ergebnis's
 * `noNamedArgument` forbids.
 */
final class AttributeArgumentsInParameterOrderFixer implements FixerInterface
{
    public function getName(): string
    {
        return 'App/attribute_arguments_in_parameter_order';
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'The named arguments of an attribute stand in the order of its constructor\'s parameters.',
            [new CodeSample("<?php\n#[Symfony\\Component\\Routing\\Attribute\\Route(name: 'start', path: '/')]\nfunction startAction() {}\n")]
        );
    }

    /**
     * After the fixers that change imports and names, by which it finds an attribute's class, and after the alignment
     * of operators; what it moves, it moves with the whitespace around it untouched, so no later fixer has to mend it.
     */
    public function getPriority(): int
    {
        return -40;
    }

    /**
     * An attribute's arguments are constant expressions, which have no effect whose order could change.
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
        return $tokens->isTokenKindFound(T_ATTRIBUTE);
    }

    public function fix(SplFileInfo $file, Tokens $tokens): void
    {
        // Every reordering keeps the number of tokens, so the indexes of the attributes after it stay valid
        for ($index = 0; $index < $tokens->count(); ++$index) {
            if ($tokens[$index]->isGivenKind(T_ATTRIBUTE)) {
                $this->fixGroup($tokens, $index, $tokens->findBlockEnd(Tokens::BLOCK_TYPE_ATTRIBUTE, $index));
            }
        }
    }

    /**
     * Orders each attribute of the group `#[First(…), Second(…)]` that opens at $open and closes at $close.
     */
    private function fixGroup(Tokens $tokens, int $open, int $close): void
    {
        $cursor = $open;
        while (true) {
            $nameStart = $tokens->getNextMeaningfulToken($cursor);
            if ($nameStart === null || $nameStart >= $close || !$this->isNamePart($tokens[$nameStart])) {
                return;
            }
            $nameEnd = $this->nameEnd($tokens, $nameStart);
            $next    = $tokens->getNextMeaningfulToken($nameEnd);
            if ($next === null) {
                return;
            }

            if ($tokens[$next]->equals('(')) {
                $closingParenthesis = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS, $next);
                $class              = $this->classOf($tokens, $tokens->generatePartialCode($nameStart, $nameEnd), $nameStart);
                $this->orderArguments(
                    $tokens,
                    $class,
                    $next,
                    $closingParenthesis
                );
                $next = $tokens->getNextMeaningfulToken($closingParenthesis);
            }

            if ($next === null || !$tokens[$next]->equals(',')) {
                return;
            }
            $cursor = $next;
        }
    }

    private function orderArguments(
        Tokens $tokens,
        string $class,
        int    $openingParenthesis,
        int    $closingParenthesis
    ): void {
        $positions = $this->parameterPositionsOf($class);
        if ($positions === null) {
            return;
        }

        $named = [];
        foreach ($this->argumentsBetween($tokens, $openingParenthesis, $closingParenthesis) as [$start, $end]) {
            $first = $tokens[$start]->isComment() ? $tokens->getNextMeaningfulToken($start) : $start;
            if ($first === null || !$tokens[$first]->isGivenKind(CT::T_NAMED_ARGUMENT_NAME)) {
                if ($named !== []) {
                    return;
                }
                continue;
            }
            $name = $tokens[$first]->getContent();
            if (!array_key_exists($name, $positions)) {
                return;
            }
            $named[] = ['position' => $positions[$name], 'start' => $start, 'end' => $end];
        }

        $ordered = $named;
        usort($ordered, static fn (array $one, array $other): int => $one['position'] <=> $other['position']);
        $first = array_first($named);
        $last  = array_last($named);
        if ($ordered === $named || $first === null || $last === null) {
            return;
        }

        // The arguments trade places; what stands between two of them, a comma and whitespace, stays
        $replacement = [];
        foreach ($named as $slot => $argument) {
            for ($index = $ordered[$slot]['start']; $index <= $ordered[$slot]['end']; ++$index) {
                $replacement[] = clone $tokens[$index];
            }
            $nextStart = $named[$slot + 1]['start'] ?? null;
            if ($nextStart !== null) {
                for ($index = $argument['end'] + 1; $index < $nextStart; ++$index) {
                    $replacement[] = clone $tokens[$index];
                }
            }
        }
        $tokens->overrideRange($first['start'], $last['end'], $replacement);
    }

    /**
     * The position of each parameter of the attribute's constructor, by its name; null where the attribute has no
     * order of its arguments to keep: its class is not found, it has no constructor, or one with a variadic parameter.
     *
     * @return array<string, int>|null
     */
    private function parameterPositionsOf(string $class): ?array
    {
        if (!class_exists($class)) {
            return null;
        }
        $constructor = new ReflectionClass($class)->getConstructor();
        if ($constructor === null) {
            return null;
        }

        $positions = [];
        foreach ($constructor->getParameters() as $parameter) {
            if ($parameter->isVariadic()) {
                return null;
            }
            $positions[$parameter->getName()] = $parameter->getPosition();
        }

        return $positions;
    }

    /**
     * The first and the last token of each argument between the parentheses, a comment in front of it included and
     * the whitespace around it excluded; a trailing comma leaves no argument after it.
     *
     * @return list<array{int, int}>
     */
    private function argumentsBetween(Tokens $tokens, int $openingParenthesis, int $closingParenthesis): array
    {
        $arguments = [];
        $start     = $openingParenthesis + 1;
        for ($index = $start; $index <= $closingParenthesis; ++$index) {
            $block = Tokens::detectBlockType($tokens[$index]);
            if ($block !== null && $block['isStart']) {
                $index = $tokens->findBlockEnd($block['type'], $index);
                continue;
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
                $arguments[] = [$first, $last];
            }
            $start = $index + 1;
        }

        return $arguments;
    }

    /**
     * The fully qualified name the attribute's name stands for at $at: by an import of its first part, else in the
     * namespace the file declares there.
     */
    private function classOf(Tokens $tokens, string $name, int $at): string
    {
        if (str_starts_with($name, '\\')) {
            return mb_substr($name, 1);
        }

        $namespace = '';
        $imports   = [];
        for ($index = 0; $index < $at; ++$index) {
            if ($tokens[$index]->isGivenKind(T_NAMESPACE)) {
                $start     = $tokens->getNextMeaningfulToken($index);
                $namespace = $start !== null && $this->isNamePart($tokens[$start])
                    ? $tokens->generatePartialCode($start, $this->nameEnd($tokens, $start))
                    : '';
                $imports = [];
            } elseif ($tokens[$index]->isGivenKind(T_USE)) {
                $imports = [...$imports, ...$this->importsOf($tokens, $index)];
            }
        }

        $separator = mb_strpos($name, '\\');
        $first     = $separator === false ? $name : mb_substr($name, 0, $separator);
        $imported  = $imports[mb_strtolower($first)] ?? null;
        if ($imported !== null) {
            return $imported . mb_substr($name, mb_strlen($first));
        }

        return $namespace === '' ? $name : "{$namespace}\\{$name}";
    }

    /**
     * The classes and namespaces the import statement at $use imports, by their alias in lower case, as PHP compares
     * class names regardless of case; an import of functions or constants imports none. A trait's or a closure's
     * `use` is a token of another kind and never comes here.
     *
     * @return array<string, string>
     */
    private function importsOf(Tokens $tokens, int $use): array
    {
        $index = $tokens->getNextMeaningfulToken($use);
        if ($index === null || $tokens[$index]->isGivenKind([CT::T_FUNCTION_IMPORT, CT::T_CONST_IMPORT])) {
            return [];
        }

        $imports = [];
        $prefix  = '';
        while ($index !== null && $this->isNamePart($tokens[$index])) {
            $end  = $this->nameEnd($tokens, $index);
            $name = $prefix . $tokens->generatePartialCode($index, $end);
            $next = $tokens->getNextMeaningfulToken($end);
            if ($next !== null && $tokens[$next]->isGivenKind(CT::T_GROUP_IMPORT_BRACE_OPEN)) {
                $prefix = $name;
                $index  = $tokens->getNextMeaningfulToken($next);
                continue;
            }

            $alias = array_last(explode('\\', $name));
            if ($next !== null && $tokens[$next]->isGivenKind(T_AS)) {
                $aliasIndex = $tokens->getNextMeaningfulToken($next);
                if ($aliasIndex === null) {
                    break;
                }
                $alias = $tokens[$aliasIndex]->getContent();
                $next  = $tokens->getNextMeaningfulToken($aliasIndex);
            }
            $imports[mb_strtolower($alias)] = ltrim($name, '\\');

            if ($next === null || !$tokens[$next]->equals(',')) {
                break;
            }
            $index = $tokens->getNextMeaningfulToken($next);
        }

        return $imports;
    }

    /**
     * The last token of the name that starts at $start, such as `ORM\Column` or `\Symfony\…\Route`, which PHP CS Fixer
     * gives as its parts and their separators.
     */
    private function nameEnd(Tokens $tokens, int $start): int
    {
        $end = $start;
        while ($end + 1 < $tokens->count() && $this->isNamePart($tokens[$end + 1])) {
            ++$end;
        }

        return $end;
    }

    private function isNamePart(Token $token): bool
    {
        return $token->isGivenKind([T_STRING, T_NS_SEPARATOR]);
    }
}
