<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\PhpCsFixer\AttributeArgumentsInParameterOrder;

use Attribute;

/**
 * An attribute whose constructor's parameters the tests of AttributeArgumentsInParameterOrderFixer order by.
 */
#[Attribute(Attribute::TARGET_ALL | Attribute::IS_REPEATABLE)]
final readonly class Probe
{
    /**
     * @param list<mixed> $third
     */
    public function __construct(
        public string $first = '',
        public string $second = '',
        public array  $third = [],
        public string $fourth = ''
    ) {
    }
}
