<?php

declare(strict_types=1);

namespace App\Alpha\Facade\Dto;

final readonly class ThingDto
{
    public function __construct(
        public ?string $doneOn
    ) {
    }

    public function isDone(): bool
    {
        return $this->doneOn !== null;
    }
}
