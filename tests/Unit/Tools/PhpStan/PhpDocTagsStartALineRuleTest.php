<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tools\PhpStan;

use PHPUnit\Framework\TestCase;

/**
 * A PHPDoc tag after text, or after another tag on its line, is reported; a tag at the start of a line, an inline
 * {@see} and a tag named in backticks are not.
 */
final class PhpDocTagsStartALineRuleTest extends TestCase
{
    use RunsPhpStan;

    private const string CONFIGURATION = <<<'NEON'
        rules:
            - App\Tools\PhpStan\Rules\PhpDocTagsStartALineRule

        NEON;
    private const string WHY = ' does not start its line, so PHPDoc reads it as text and its type is lost: put every tag on a line of its own.';

    public function testReportsEveryTagThatDoesNotStartItsLine(): void
    {
        $this->assertSame(
            [
                'Things.php:12 The PHPDoc tag @return' . self::WHY,
                'Things.php:18 The PHPDoc tag @return' . self::WHY,
                'Things.php:47 The PHPDoc tag @var' . self::WHY,
            ],
            $this->phpStanErrors(self::CONFIGURATION, dirname(__DIR__, 3) . '/Fixtures/PhpStan/PhpDocTagsStartALine')
        );
    }
}
