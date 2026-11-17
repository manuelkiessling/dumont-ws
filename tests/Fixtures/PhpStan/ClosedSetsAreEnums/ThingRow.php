<?php

declare(strict_types=1);

namespace App\Alpha\Infrastructure\ReadModel;

/**
 * Where the outside defines the value, a column here, a kind stays the string it is stored as.
 */
final class ThingRow
{
    public function __construct(
        public string $kind,
        public ?string $subjectKind
    ) {
    }

    public function kind(): string
    {
        return $this->kind;
    }
}
