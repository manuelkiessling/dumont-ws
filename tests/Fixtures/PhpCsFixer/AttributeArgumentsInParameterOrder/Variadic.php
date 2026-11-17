<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\PhpCsFixer\AttributeArgumentsInParameterOrder;

use Attribute;

/**
 * An attribute whose variadic parameter takes named arguments under any name, which have no place in its order.
 */
#[Attribute(Attribute::TARGET_ALL)]
final readonly class Variadic
{
    /**
     * @var list<string>
     */
    public array $rest;

    public function __construct(
        public string $first = '',
        string        ...$rest
    ) {
        $this->rest = array_values($rest);
    }
}
