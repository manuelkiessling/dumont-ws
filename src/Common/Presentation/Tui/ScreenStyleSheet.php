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
 * The look every screen shares: a cyan border where the focus is, a gray one elsewhere.
 */
final class ScreenStyleSheet
{
    public static function create(): StyleSheet
    {
        $border     = Border::all(1, BorderPattern::ROUNDED, 'gray');
        $focusedBox = new Style()->withBorder(Border::all(1, BorderPattern::ROUNDED, 'cyan'));

        return new StyleSheet([
            ':root'                            => new Style()->withPadding(Padding::xy(2, 1))->withGap(1),
            '.title'                           => new Style()->withBold()->withColor('cyan'),
            InputWidget::class                 => new Style(Padding::xy(2), $border),
            InputWidget::class . ':focus'      => $focusedBox,
            SelectListWidget::class            => new Style(Padding::xy(2, 1), $border),
            SelectListWidget::class . ':focus' => $focusedBox,
            '.empty-list'                      => new Style(Padding::xy(2, 1), $border)->withColor('gray'),
            '.notice'                          => new Style()->withBold()->withColor('yellow'),
            '.hidden'                          => new Style()->withHidden(),
            '.help'                            => new Style()->withColor('gray'),
        ]);
    }
}
