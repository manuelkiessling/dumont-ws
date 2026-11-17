<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\PhpCsFixer\AttributeArgumentsInParameterOrder;

use Attribute;

/**
 * An attribute without a constructor, whose arguments AttributeArgumentsInParameterOrderFixer has no order for.
 */
#[Attribute(Attribute::TARGET_ALL)]
final readonly class WithoutConstructor
{
}
