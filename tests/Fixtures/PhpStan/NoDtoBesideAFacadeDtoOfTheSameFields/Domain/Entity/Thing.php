<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\PhpStan\NoDtoBesideAFacadeDtoOfTheSameFields\Domain\Entity;

// No DTO: an entity is converted, which is a facade's work
final class Thing
{
    public function __construct(
        public string $id,
        public string $label
    ) {
    }
}
