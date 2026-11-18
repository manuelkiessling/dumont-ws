<?php

declare(strict_types=1);

namespace App\Tests\Unit\Common;

use App\Common\Presentation\Tui\TerminalText;
use PHPUnit\Framework\TestCase;

final class TerminalTextTest extends TestCase
{
    public function testKeepsTextAsItIs(): void
    {
        self::assertSame("Grüße an Oma\tund\nOpa", TerminalText::withoutControls("Grüße an Oma\tund\nOpa"));
    }

    public function testRemovesTheIntroducersOfTerminalEscapes(): void
    {
        self::assertSame('[2JBuy milk', TerminalText::withoutControls("\x1b[2JBuy milk\x07"));
        self::assertSame('Buy milk', TerminalText::withoutControls("Buy\xc2\x9b milk\x7f"));
    }

    public function testRemovesWhatIsNoUtf8(): void
    {
        self::assertSame('Buy ?milk', TerminalText::withoutControls("Buy \xc2milk"));
    }
}
