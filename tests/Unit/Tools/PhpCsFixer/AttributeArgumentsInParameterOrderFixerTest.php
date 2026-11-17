<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tools\PhpCsFixer;

use App\Tools\PhpCsFixer\Fixers\AttributeArgumentsInParameterOrderFixer;
use PhpCsFixer\Tokenizer\Tokens;
use PHPUnit\Framework\TestCase;
use SplFileInfo;

final class AttributeArgumentsInParameterOrderFixerTest extends TestCase
{
    private const string HEAD = <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace App\Tests\Fixtures\PhpCsFixer\AttributeArgumentsInParameterOrder;

        use Symfony\Component\HttpFoundation\Request;
        use Symfony\Component\Routing\Attribute\Route;


        PHP;

    public function testPutsTheNamedArgumentsOfAMultilineAttributeInTheOrderOfItsConstructorAndKeepsTheirLayout(): void
    {
        $this->assertSame(
            self::HEAD . <<<'PHP'
                final class Controller
                {
                    #[Route(
                        path    : '/companion',
                        name    : 'companion.presentation.handshake',
                        defaults: ['_format' => 'json'],
                        methods : [Request::METHOD_GET]
                    )]
                    public function handshakeAction(): void
                    {
                    }
                }
                PHP,
            $this->fixed(self::HEAD . <<<'PHP'
                final class Controller
                {
                    #[Route(
                        path    : '/companion',
                        name    : 'companion.presentation.handshake',
                        methods : [Request::METHOD_GET],
                        defaults: ['_format' => 'json']
                    )]
                    public function handshakeAction(): void
                    {
                    }
                }
                PHP)
        );
    }

    public function testPutsTheNamedArgumentsOfAOneLineAttributeInOrderAndLeavesThePositionalOnesInFront(): void
    {
        $this->assertSame(
            self::HEAD . "#[Probe('a', third: [1, 2], fourth: 'd')]\nfinal class Thing\n{\n}\n",
            $this->fixed(self::HEAD . "#[Probe('a', fourth: 'd', third: [1, 2])]\nfinal class Thing\n{\n}\n")
        );
    }

    public function testFindsTheAttributesClassByItsImportAnAliasAFullyQualifiedNameAndTheFilesNamespace(): void
    {
        $code = <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace App\Tests\Fixtures\PhpCsFixer\AttributeArgumentsInParameterOrder;

            use App\Tests\Fixtures\PhpCsFixer as Fixtures;
            use App\Tests\Fixtures\PhpCsFixer\AttributeArgumentsInParameterOrder\Probe as Aliased;

            #[Probe(second: 'b', first: 'a')]
            #[Aliased(second: 'b', first: 'a')]
            #[Fixtures\AttributeArgumentsInParameterOrder\Probe(second: 'b', first: 'a')]
            #[\App\Tests\Fixtures\PhpCsFixer\AttributeArgumentsInParameterOrder\Probe(second: 'b', first: 'a')]
            final class Thing
            {
            }

            PHP;

        $this->assertSame(
            str_replace("(second: 'b', first: 'a')", "(first: 'a', second: 'b')", $code),
            $this->fixed($code)
        );
    }

    public function testOrdersEveryAttributeOfAGroupAndOfEveryPlaceAnAttributeStands(): void
    {
        $this->assertSame(
            self::HEAD . <<<'PHP'
                #[Probe(first: 'a', second: 'b'), Probe(third: [], fourth: 'd')]
                final class Thing
                {
                    #[Probe(first: 'a', fourth: 'd')]
                    private string $property = '';

                    public function method(#[Probe(second: 'b', third: [])] string $parameter): void
                    {
                        $closure = #[Probe(first: 'a', second: 'b')] static fn (): string => $parameter;
                    }
                }
                PHP,
            $this->fixed(self::HEAD . <<<'PHP'
                #[Probe(second: 'b', first: 'a'), Probe(fourth: 'd', third: [])]
                final class Thing
                {
                    #[Probe(fourth: 'd', first: 'a')]
                    private string $property = '';

                    public function method(#[Probe(third: [], second: 'b')] string $parameter): void
                    {
                        $closure = #[Probe(second: 'b', first: 'a')] static fn (): string => $parameter;
                    }
                }
                PHP)
        );
    }

    public function testMovesAnArgumentWholeWithTheCommasInsideItAndTheCommentInFrontOfIt(): void
    {
        $this->assertSame(
            self::HEAD . <<<'PHP'
                #[Probe(
                    first : 'a',
                    third : [1, [2, 3], Request::METHOD_GET],
                    // the last
                    fourth: 'd',
                )]
                final class Thing
                {
                }
                PHP,
            $this->fixed(self::HEAD . <<<'PHP'
                #[Probe(
                    // the last
                    fourth: 'd',
                    first : 'a',
                    third : [1, [2, 3], Request::METHOD_GET],
                )]
                final class Thing
                {
                }
                PHP)
        );
    }

    public function testLeavesAnAttributeAloneThatItHasNoOrderFor(): void
    {
        $code = self::HEAD . <<<'PHP'
            #[NoSuchAttribute(second: 'b', first: 'a')]
            #[Probe(second: 'b', unknown: 'x', first: 'a')]
            #[WithoutConstructor(second: 'b', first: 'a')]
            #[Variadic(other: 'b', first: 'a')]
            #[Probe(first: 'a', second: 'b')]
            #[Probe('a')]
            #[Probe]
            final class Thing
            {
                public function method(): void
                {
                    $this->other(second: 'b', first: 'a');
                }

                private function other(string $first, string $second): void
                {
                }
            }
            PHP;

        $this->assertSame($code, $this->fixed($code));
    }

    public function testIsACandidateOnlyForCodeWithAttributes(): void
    {
        $fixer = new AttributeArgumentsInParameterOrderFixer();

        $this->assertTrue($fixer->isCandidate(Tokens::fromCode(self::HEAD . "#[Probe]\nfinal class Thing\n{\n}\n")));
        $this->assertFalse($fixer->isCandidate(Tokens::fromCode(self::HEAD . "final class Thing\n{\n}\n")));
    }

    private function fixed(string $code): string
    {
        $tokens = Tokens::fromCode($code);
        new AttributeArgumentsInParameterOrderFixer()->fix(new SplFileInfo(__FILE__), $tokens);

        return $tokens->generateCode();
    }
}
