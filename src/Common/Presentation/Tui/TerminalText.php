<?php

declare(strict_types=1);

namespace App\Common\Presentation\Tui;

/**
 * Text from the data, made safe to show in the terminal: a widget renders its text as it is, so a control character in
 * an item's text could move the cursor or change the terminal's state. The TUI component's own helper for this is
 * internal to it.
 */
final class TerminalText
{
    /**
     * The text as valid UTF-8, without C0 control characters (but for tab and line feed), DEL, and C1 control
     * characters.
     */
    public static function withoutControls(string $text): string
    {
        return preg_replace('/[\x00-\x08\x0B-\x1F\x7F]|\xC2[\x80-\x9F]/', '', mb_scrub($text, 'UTF-8')) ?? '';
    }
}
