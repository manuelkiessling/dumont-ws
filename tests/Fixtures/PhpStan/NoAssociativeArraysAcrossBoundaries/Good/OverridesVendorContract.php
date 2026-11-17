<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\PhpStan\NoAssociativeArraysAcrossBoundaries\Good;

use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Widget\AbstractWidget;

final class OverridesVendorContract extends AbstractWidget
{
    /**
     * @return string[]
     */
    public function render(RenderContext $context): array
    {
        return ['A line of the widget'];
    }
}
