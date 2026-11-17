<?php

declare(strict_types=1);

namespace App\Common\Presentation\Tui;

use Symfony\Component\Tui\Style\Border;
use Symfony\Component\Tui\Style\BorderPattern;
use Symfony\Component\Tui\Style\Padding;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Widget\InputWidget;
use Symfony\Component\Tui\Widget\SelectListWidget;

/**
 * The look every screen shares. A screen marks its widgets with these classes: `title`, `notice`, `empty-list`,
 * `help`, and `hidden` for a widget that takes no space.
 */
final class ScreenStyleSheet
{
    public static function create(): StyleSheet
    {
        $border     = Border::all(1, BorderPattern::ROUNDED, 'gray');
        $focusedBox = new Style(border: Border::all(1, BorderPattern::ROUNDED, 'cyan'));

        return new StyleSheet([
            ':root'                            => new Style(padding: Padding::xy(2, 1), gap: 1),
            '.title'                           => new Style(bold: true, color: 'cyan'),
            InputWidget::class                 => new Style(Padding::xy(2), $border),
            InputWidget::class . ':focus'      => $focusedBox,
            SelectListWidget::class            => new Style(Padding::xy(2, 1), $border),
            SelectListWidget::class . ':focus' => $focusedBox,
            '.empty-list'                      => new Style(Padding::xy(2, 1), $border, color: 'gray'),
            '.notice'                          => new Style(bold: true, color: 'yellow'),
            '.hidden'                          => new Style(hidden: true),
            '.help'                            => new Style(color: 'gray'),
        ]);
    }
}
