<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\PhpStan\DomainReachesOutThroughInterfaces\Facade\Dto;

final readonly class ThingDto
{
    public function __construct(
        public string $name
    ) {
    }
}
