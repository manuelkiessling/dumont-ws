<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\PhpStan\NoDtoBesideAFacadeDtoOfTheSameFields\Facade\Dto;

final readonly class ThingDto
{
    public function __construct(
        public string $id,
        public string $label
    ) {
    }
}
