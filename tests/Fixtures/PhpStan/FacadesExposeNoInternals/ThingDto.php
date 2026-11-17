<?php

declare(strict_types=1);

namespace App\Alpha\Facade\Dto;

use App\Alpha\Domain\Model\Thing;
use App\Common\Domain\Enum\Timezone;
use DateTimeImmutable;

final readonly class ThingDto
{
    public function __construct(
        public string $id,
        public Timezone $timezone,
        public ?DateTimeImmutable $at,
        public Thing $thing,
        private Thing $privateThing
    ) {
    }

    public function privateThingId(): string
    {
        return $this->privateThing::class;
    }
}
