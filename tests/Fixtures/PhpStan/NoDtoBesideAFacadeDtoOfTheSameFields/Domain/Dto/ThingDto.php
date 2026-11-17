<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\PhpStan\NoDtoBesideAFacadeDtoOfTheSameFields\Domain\Dto;

// The same fields as the facade's DTO, and nothing else
final readonly class ThingDto
{
    public function __construct(
        public string $id,
        public string $label
    ) {
    }
}
