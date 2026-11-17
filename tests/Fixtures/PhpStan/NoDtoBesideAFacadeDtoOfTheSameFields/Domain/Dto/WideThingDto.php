<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\PhpStan\NoDtoBesideAFacadeDtoOfTheSameFields\Domain\Dto;

// One field more than the facade's DTO tells other verticals
final readonly class WideThingDto
{
    public function __construct(
        public string $id,
        public string $label,
        public string $internalNote
    ) {
    }
}
