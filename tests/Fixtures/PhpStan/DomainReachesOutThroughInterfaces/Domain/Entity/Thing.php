<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\PhpStan\DomainReachesOutThroughInterfaces\Domain\Entity;

use App\Tests\Fixtures\PhpStan\DomainReachesOutThroughInterfaces\Facade\Dto\ThingDto;
use App\Tests\Fixtures\PhpStan\DomainReachesOutThroughInterfaces\Facade\Enum\Colour;
use DateTimeImmutable;

final class Thing
{
    public function __construct(
        public DateTimeImmutable $madeAt,
        public Colour $colour,
        public ThingDto $dto
    ) {
    }
}
