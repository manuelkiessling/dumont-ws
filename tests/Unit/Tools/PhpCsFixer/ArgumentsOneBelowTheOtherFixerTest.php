<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tools\PhpCsFixer;

use App\Tools\PhpCsFixer\Fixers\ArgumentsOneBelowTheOtherFixer;
use PhpCsFixer\Tokenizer\Tokens;
use PHPUnit\Framework\TestCase;
use SplFileInfo;

final class ArgumentsOneBelowTheOtherFixerTest extends TestCase
{
    public function testPutsFourParametersOfAMethodOneBelowTheOther(): void
    {
        $this->assertSame(
            <<<'PHP'
                <?php
                final class Thing
                {
                    private function closeIfEnded(
                        Admission $system,
                        RepresentationDto $representation,
                        ?MembershipDto $membership,
                        string $day
                    ): bool
                    {
                        return true;
                    }
                }
                PHP,
            $this->fixed(<<<'PHP'
                <?php
                final class Thing
                {
                    private function closeIfEnded(Admission $system, RepresentationDto $representation, ?MembershipDto $membership, string $day): bool
                    {
                        return true;
                    }
                }
                PHP)
        );
    }

    public function testPutsFourArgumentsOfACallAndOfNewOneBelowTheOther(): void
    {
        $this->assertSame(
            <<<'PHP'
                <?php
                function decide(): void
                {
                    $decision = new PlacementDecision(
                        [],
                        self::titleOf(null, $message->opening),
                        SystemBy::Rule,
                        self::NEW_TOPIC,
                        false
                    );
                    $this->service->record(
                        $a,
                        $b,
                        $c,
                        $d
                    );
                }
                PHP,
            $this->fixed(<<<'PHP'
                <?php
                function decide(): void
                {
                    $decision = new PlacementDecision([], self::titleOf(null, $message->opening), SystemBy::Rule, self::NEW_TOPIC, false);
                    $this->service->record($a, $b, $c, $d);
                }
                PHP)
        );
    }

    public function testLeavesAListOfThreeAsItIsOnOneLineOrOnMany(): void
    {
        $code = <<<'PHP'
            <?php
            function three(string $a, string $b, string $c): void
            {
                sprintf('%s %s', $a, $b);
                sprintf(
                    '%s %s',
                    $a, $b
                );
            }
            PHP;

        $this->assertSame($code, $this->fixed($code));
    }

    public function testLeavesAListThatAlreadyStandsOneBelowTheOtherAsItIs(): void
    {
        $code = <<<'PHP'
            <?php
            record(
                $a,
                $b,
                $c,
                $d,
            );
            PHP;

        $this->assertSame($code, $this->fixed($code));
    }

    public function testCompletesAListOfFourThatStandsOnSomeLinesOnly(): void
    {
        $this->assertSame(
            <<<'PHP'
                <?php
                $text = sprintf(
                    '%s %s %s',
                    $a,
                    $b,
                    $c
                );
                PHP,
            $this->fixed(<<<'PHP'
                <?php
                $text = sprintf('%s %s %s',
                    $a, $b, $c);
                PHP)
        );
    }

    public function testCountsATrailingCommaAsNoElement(): void
    {
        $code = "<?php\nrecord(\$a, \$b, \$c,);\n";

        $this->assertSame($code, $this->fixed($code));
    }

    public function testMovesAnElementOverSeveralLinesWithItsLinesAndIndentsAListInsideAListFromItsNewLine(): void
    {
        $this->assertSame(
            <<<'PHP'
                <?php
                function outer(): void
                {
                    record(
                        $a,
                        $b,
                        inner(
                            $c,
                            $d,
                            $e,
                            $f
                        ),
                        static function (): array {
                            return [
                                'key' => $value,
                            ];
                        }
                    );
                }
                PHP,
            $this->fixed(<<<'PHP'
                <?php
                function outer(): void
                {
                    record($a, $b, inner($c, $d, $e, $f), static function (): array {
                        return [
                            'key' => $value,
                        ];
                    });
                }
                PHP)
        );
    }

    public function testCoversClosuresArrowFunctionsTheUseOfAClosureAndAttributes(): void
    {
        $this->assertSame(
            <<<'PHP'
                <?php
                #[Probe(
                    'a',
                    'b',
                    'c',
                    'd'
                )]
                final class Thing
                {
                }
                $closure = function (
                    $a,
                    $b,
                    $c,
                    $d
                ) use (
                    $e,
                    $f,
                    $g,
                    $h
                ): void {
                };
                $arrow = fn (
                    $a,
                    $b,
                    $c,
                    $d
                ): int => 1;
                PHP,
            $this->fixed(<<<'PHP'
                <?php
                #[Probe('a', 'b', 'c', 'd')]
                final class Thing
                {
                }
                $closure = function ($a, $b, $c, $d) use ($e, $f, $g, $h): void {
                };
                $arrow = fn ($a, $b, $c, $d): int => 1;
                PHP)
        );
    }

    public function testLeavesConditionsArraysAndTheLanguagesConstructsAlone(): void
    {
        $code = <<<'PHP'
            <?php
            if ($a && ($b || $c)) {
                $list = array(1, 2, 3, 4);
                [$a, $b, $c, $d] = $list;
                isset($a, $b, $c, $d);
                for ($i = 0, $j = 1, $k = 2, $l = 3; $i < 1; ++$i) {
                }
            }
            PHP;

        $this->assertSame($code, $this->fixed($code));
    }

    public function testLeavesAListWithACommentBetweenItsElementsAlone(): void
    {
        $code = "<?php\nrecord(\$a, /* the second */ \$b, \$c, \$d);\n";

        $this->assertSame($code, $this->fixed($code));
    }

    public function testPutsTwoOrThreeArgumentsOneBelowTheOtherOnALineLongerThan120CharactersTheOutermostListFirst(): void
    {
        $this->assertSame(
            <<<'PHP'
                <?php
                function reachOn(): ReachSnapshot
                {
                    return $this->answers->of(
                        CapacityQuestion::ReachOn,
                        [$workspaceId, $day, $this->limit],
                        fn (): ReachSnapshot => $this->computedSnapshotOn($workspaceId, $day)
                    );
                }
                PHP,
            $this->fixed(<<<'PHP'
                <?php
                function reachOn(): ReachSnapshot
                {
                    return $this->answers->of(CapacityQuestion::ReachOn, [$workspaceId, $day, $this->limit], fn (): ReachSnapshot => $this->computedSnapshotOn($workspaceId, $day));
                }
                PHP)
        );
    }

    public function testPutsTheLongestListOfALongLineOneBelowTheOtherAndLeavesTheShorterOnesBeforeIt(): void
    {
        $this->assertSame(
            <<<'PHP'
                <?php
                $text = sprintf('%s', $a) . implode(', ', $list) . $this->formatter->describedAt(
                    $theOccurrenceThatIsDescribed,
                    $theMomentOfTheDescription
                );
                PHP,
            $this->fixed(<<<'PHP'
                <?php
                $text = sprintf('%s', $a) . implode(', ', $list) . $this->formatter->describedAt($theOccurrenceThatIsDescribed, $theMomentOfTheDescription);
                PHP)
        );
    }

    public function testPutsTheShorterListsOfALongLineOneBelowTheOtherToo(): void
    {
        $this->assertSame(
            <<<'PHP'
                <?php
                $text = $this->formatter->describedAt(
                    $theOccurrenceThatIsDescribed,
                    $theMomentOfTheDescription
                ) . $this->formatter->describedAt(
                    $theOccurrenceThatIsDescribedLater,
                    $theMomentOfTheLaterDescription
                );
                PHP,
            $this->fixed(<<<'PHP'
                <?php
                $text = $this->formatter->describedAt($theOccurrenceThatIsDescribed, $theMomentOfTheDescription) . $this->formatter->describedAt($theOccurrenceThatIsDescribedLater, $theMomentOfTheLaterDescription);
                PHP)
        );
    }

    public function testPutsEachLinkOfAChainOfCallsOnALongLineOnALineOfItsOwn(): void
    {
        $this->assertSame(
            <<<'PHP'
                <?php
                function articles(): array
                {
                    return $this->entityManager->createQueryBuilder()
                        ->select('article')
                        ->from(NewsArticle::class, 'article')
                        ->where('article.workspaceId = :workspace')
                        ->setParameter('workspace', $context->workspaceId)
                        ->getQuery()
                        ->getResult();
                }
                PHP,
            $this->fixed(<<<'PHP'
                <?php
                function articles(): array
                {
                    return $this->entityManager->createQueryBuilder()->select('article')->from(NewsArticle::class, 'article')->where('article.workspaceId = :workspace')->setParameter('workspace', $context->workspaceId)->getQuery()->getResult();
                }
                PHP)
        );
    }

    public function testMovesTheBodyOfAClosureTheLastLinkOfAChainOpensWithTheLink(): void
    {
        $this->assertSame(
            <<<'PHP'
                <?php
                $this->entityManager->createQueryBuilder()
                    ->select('article')
                    ->from(NewsArticle::class, 'article')
                    ->each(function ($a): void {
                        record($a);
                    });
                PHP,
            $this->fixed(<<<'PHP'
                <?php
                $this->entityManager->createQueryBuilder()->select('article')->from(NewsArticle::class, 'article')->each(function ($a): void {
                    record($a);
                });
                PHP)
        );
    }

    public function testLeavesAListThatStandsOneBelowTheOtherOnALineThatStaysLongAsItIs(): void
    {
        $code = <<<'PHP'
            <?php
            $text = 'a string that makes the line that opens the list longer than one hundred and twenty characters' . record(
                $a,
                $b,
                $c,
                $d
            );
            PHP;

        $this->assertSame($code, $this->fixed($code));
    }

    public function testLeavesALineThatIsLongByAStringOrByAListOfOneElementLong(): void
    {
        $code = <<<'PHP'
            <?php
            $message = $this->translator->trans('a message that is long enough to make this line longer than one hundred and twenty characters');
            $files   = new RecursiveIteratorIterator(new RecursiveDirectoryIteratorOfAVeryLongNameIndeed(new SplFileInfoOfAnEvenLongerNameThanThat()));
            PHP;

        $this->assertSame($code, $this->fixed($code));
    }

    public function testLeavesAListOfTwoOrThreeThatStandsOnSeveralLinesAsItIs(): void
    {
        $code = <<<'PHP'
            <?php
            $this->businessOperations->run(AccountUseCase::VerifyAddress, function () use ($nobodySignedIn, $accountCoreId): bool {
                return true;
            });
            PHP;

        $this->assertSame($code, $this->fixed($code));
    }

    public function testIsACandidateOnlyForCodeWithACommaInIt(): void
    {
        $fixer = new ArgumentsOneBelowTheOtherFixer();

        $this->assertTrue($fixer->isCandidate(Tokens::fromCode("<?php\nrecord(\$a, \$b);\n")));
        $this->assertFalse($fixer->isCandidate(Tokens::fromCode("<?php\nrecord(\$a);\n")));
    }

    private function fixed(string $code): string
    {
        $tokens = Tokens::fromCode($code);
        new ArgumentsOneBelowTheOtherFixer()->fix(new SplFileInfo(__FILE__), $tokens);

        return $tokens->generateCode();
    }
}
